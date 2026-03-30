<?php

declare(strict_types=1);

namespace Thesis\HotReload\Internal;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\DeferredFuture;
use Revolt\EventLoop;
use function Thesis\exceptionally;

/**
 * Runs a command transparently: inherits stdin/stdout/stderr from the parent
 * process and forwards signals to the child.
 *
 * @internal
 */
final class TtyProcess
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
     * @return non-negative-int
     */
    public static function start(
        array $command,
        Cancellation $termination,
        float $terminationTimeout = 10,
    ): int {
        /** @var DeferredFuture<non-negative-int> */
        $deferred = new DeferredFuture();

        /** @var ?self */
        $process = null;

        // Register before proc_open so SIGCHLD cannot slip through between the
        // process start and handler registration. $process is captured by
        // reference and filled in right after proc_open.
        $callbackId = EventLoop::onSignal(SIGCHLD, static function () use ($deferred, &$process): void {
            if ($process === null) {
                return;
            }

            $exitCode = $process->exitCode;

            if ($exitCode < 0) {
                return;
            }

            if (!$deferred->isComplete()) {
                $deferred->complete($exitCode);
            }

            $process->close();
        });

        $process = new self(
            process: exceptionally(static fn() => proc_open(
                command: $command,
                descriptor_spec: [STDIN, STDOUT, STDERR],
                pipes: $_,
            )),
            callbackIds: [$callbackId],
        );

        foreach (self::FORWARDED_SIGNALS as $signal) {
            $process->callbackIds[] = EventLoop::onSignal($signal, static fn() => $process->dispatchSignal($signal));
        }

        $terminationId = $termination->subscribe(
            static function (CancelledException $exception) use ($deferred, $process, $terminationTimeout): void {
                $process->dispatchSignal(SIGTERM);

                $process->callbackIds[] = EventLoop::delay($terminationTimeout, static function () use ($process): void {
                    $process->dispatchSignal(SIGKILL);
                });

                $deferred->error($exception);
            },
        );

        try {
            return $deferred->getFuture()->await();
        } finally {
            $termination->unsubscribe($terminationId);
        }
    }

    public int $exitCode {
        get => proc_get_status($this->process)['exitcode'];
    }

    /**
     * @param resource $process
     * @param list<string> $callbackIds
     */
    private function __construct(
        private readonly mixed $process,
        private array $callbackIds,
    ) {}

    private function dispatchSignal(int $signal): void
    {
        exceptionally(fn() => proc_terminate($this->process, $signal));
    }

    private function close(): void
    {
        try {
            proc_close($this->process);
        } catch (\Throwable) {
            // noop
        } finally {
            array_walk($this->callbackIds, EventLoop::cancel(...));
        }
    }
}
