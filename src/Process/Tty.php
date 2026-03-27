<?php

declare(strict_types=1);

namespace Thesis\HotReload\Process;

use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\NullCancellation;
use Revolt\EventLoop;
use Thesis\HotReload\Process;
use function Thesis\exceptionally;

/**
 * @api
 */
final readonly class Tty implements Process
{
    private const array FORWARDED_SIGNALS = [
        SIGHUP,   // terminal hangup / reload config
        SIGINT,   // Ctrl+C
        SIGQUIT,  // Ctrl+\ (quit + core dump)
        SIGTERM,  // graceful termination
        SIGUSR1,  // user-defined
        SIGUSR2,  // user-defined
        SIGCONT,  // continue after stop
        SIGTSTP,  // Ctrl+Z
        SIGWINCH, // terminal resize
    ];

    /**
     * @param string|list<string> $command
     */
    public static function start(string|array $command): self
    {
        if (\is_array($command)) {
            $command = implode(' ', array_map(\escapeshellarg(...), $command));
        }

        $process = exceptionally(static fn() => proc_open(
            command: $command,
            descriptor_spec: [STDIN, STDOUT, STDERR],
            pipes: $_,
        ));

        $pid = proc_get_status($process)['pid'];

        $callbackIds = array_map(
            static fn(int $signal) => EventLoop::onSignal(
                signal: $signal,
                closure: static function () use ($pid, $signal): void {
                    exceptionally(static fn() => posix_kill($pid, $signal));
                },
            ),
            self::FORWARDED_SIGNALS,
        );

        /** @var DeferredFuture<non-negative-int> */
        $deferred = new DeferredFuture();

        $check = static function () use ($process, &$callbackIds, $deferred): void {
            $status = proc_get_status($process);

            if ($status['running']) {
                return;
            }

            array_walk($callbackIds, EventLoop::cancel(...));

            proc_close($process);

            /** @phpstan-ignore cast.useless */
            $deferred->complete(max(0, (int) $status['exitcode']));
        };

        $callbackIds[] = EventLoop::onSignal(SIGCHLD, $check);

        // The process may exit before the SIGCHLD handler is registered, so check immediately
        $check();

        return new self(
            process: $process,
            run: $deferred->getFuture(),
        );
    }

    /**
     * @param resource $process
     * @param Future<non-negative-int> $run
     */
    private function __construct(
        private mixed $process,
        private Future $run,
    ) {}

    public function terminate(): void
    {
        if (!$this->run->isComplete()) {
            exceptionally(fn() => proc_terminate($this->process));
        }
    }

    public function await(Cancellation $cancellation = new NullCancellation()): int
    {
        return $this->run->await($cancellation);
    }
}
