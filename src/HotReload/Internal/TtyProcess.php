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
     * @param non-empty-list<non-empty-string> $command
     */
    public function __construct(
        private array $command,
    ) {}

    /**
     * @return non-negative-int
     */
    public function __invoke(Cancellation $cancellation): int
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

        $cancellationId = $cancellation->subscribe(static function () use ($process): void {
            exceptionally(static fn() => proc_terminate($process));
        });

        /** @var DeferredFuture<non-negative-int> */
        $deferred = new DeferredFuture();

        $checkStatus = static function () use ($process, $deferred): void {
            $status = proc_get_status($process);

            if ($status['running'] || $deferred->isComplete()) {
                return;
            }

            /** @phpstan-ignore cast.useless */
            $deferred->complete(max(0, (int) $status['exitcode']));
        };

        $callbackIds[] = EventLoop::onSignal(SIGCHLD, $checkStatus);

        // The process may exit before the SIGCHLD handler is registered, so check immediately
        $checkStatus();

        try {
            // do not pass $cancellation to await()
            // to make sure the process is finished before throwing the cancellation
            $exitCode = $deferred->getFuture()->await();

            $cancellation->throwIfRequested();

            return $exitCode;
        } finally {
            proc_close($process);

            array_walk($callbackIds, EventLoop::cancel(...));
            $cancellation->unsubscribe($cancellationId);
        }
    }
}
