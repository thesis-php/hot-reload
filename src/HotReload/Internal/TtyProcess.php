<?php

declare(strict_types=1);

namespace Thesis\HotReload\Internal;

use Amp\Cancellation;
use Amp\DeferredFuture;
use Revolt\EventLoop;
use function Thesis\exceptionally;

/**
 * @internal
 */
final readonly class TtyProcess
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
    public function __construct(
        private string|array $command,
    ) {}

    public function run(Cancellation $cancellation): int
    {
        $process = exceptionally(fn() => proc_open(
            command: $this->command,
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

        $cancellationId = $cancellation->subscribe(static function () use ($process): void {
            exceptionally(static fn() => proc_terminate($process));
        });

        $checkStatus = static function () use ($process, $deferred): void {
            $status = proc_get_status($process);

            if (!$status['running']) {
                /** @phpstan-ignore cast.useless */
                $deferred->complete(max(0, (int) $status['exitcode']));
            }
        };

        $callbackIds[] = EventLoop::onSignal(SIGCHLD, $checkStatus);

        // The process may exit before the SIGCHLD handler is registered, so check immediately
        $checkStatus();

        try {
            return $deferred->getFuture()->await($cancellation);
        } finally {
            proc_close($process);

            array_walk($callbackIds, EventLoop::cancel(...));
            $cancellation->unsubscribe($cancellationId);
        }
    }
}
