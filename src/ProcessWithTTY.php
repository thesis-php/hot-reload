<?php

declare(strict_types=1);

namespace Thesis\HotReload;

use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\NullCancellation;
use Revolt\EventLoop;
use function Thesis\exceptionally;

final class ProcessWithTTY
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
            pipes: $pipes,
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

            $exitCode = $status['exitcode'];
            \assert($exitCode >= 0);

            $deferred->complete($exitCode);
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
        private readonly mixed $process,
        private readonly Future $run,
    ) {}

    public bool $isRunning {
        get => !$this->run->isComplete();
    }

    /**
     * @return non-negative-int
     */
    public function terminate(Cancellation $cancellation = new NullCancellation()): int
    {
        if (!$this->run->isComplete()) {
            exceptionally(fn() => proc_terminate($this->process));
        }

        return $this->run->await($cancellation);
    }
}
