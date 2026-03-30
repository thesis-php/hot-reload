<?php

declare(strict_types=1);

namespace Thesis\HotReload\Internal;

use Amp\Cancellation;
use Amp\CancelledException;
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
        private float $terminateTimeout = 10,
    ) {}

    /**
     * @return non-negative-int
     */
    public function __invoke(Cancellation $cancellation): int
    {
        /** @var DeferredFuture<non-negative-int> */
        $deferred = new DeferredFuture();

        /** @var ?resource */
        $process = null;

        EventLoop::onSignal(SIGCHLD, static function (string $id) use (&$process, $deferred): void {
            if ($process === null) {
                return;
            }

            $status = proc_get_status($process);

            if ($status['running']) {
                return;
            }

            if (!$deferred->isComplete()) {
                /** @phpstan-ignore cast.useless */
                $deferred->complete(max(0, (int) $status['exitcode']));
            }

            try {
                proc_close($process);
            } finally {
                EventLoop::cancel($id);
            }
        });

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

        $terminateTimeout = $this->terminateTimeout;

        $cancellationId = $cancellation->subscribe(
            static function (CancelledException $exception) use ($process, $deferred, $pid, $terminateTimeout): void {
                exceptionally(static fn() => proc_terminate($process));

                $deferred->error($exception);

                EventLoop::delay($terminateTimeout, static function () use ($pid): void {
                    exceptionally(static fn() => posix_kill($pid, SIGKILL));
                });
            },
        );

        try {
            return $deferred->getFuture()->await();
        } finally {
            array_walk($callbackIds, EventLoop::cancel(...));
            $cancellation->unsubscribe($cancellationId);
        }
    }
}
