<?php

declare(strict_types=1);

namespace Thesis\HotReload;

use Amp\Cancellation;
use Amp\NullCancellation;
use Thesis\HotReload\ChangeDetector\MtimePolling;
use function Amp\ByteStream\getStderr;

/**
 * @api
 */
final readonly class Watcher
{
    public function __construct(
        private ChangeDetector $changeDetector = new MtimePolling(),
    ) {}

    /**
     * @param callable(): Process $start
     * @return non-negative-int
     */
    public function watch(Target $target, callable $start, float $debounce = 0.1, Cancellation $cancellation = new NullCancellation()): int
    {
        do {
            $process = $start();

            $changed = false;

            $id = $this->changeDetector->onChanged(
                target: $target,
                listener: debounce(static function () use ($process, &$changed): void {
                    getStderr()->write("\n\033[33m  ➜ Files changed, reloading...\033[0m\n\n");

                    $changed = true;

                    $process->terminate();
                }, $debounce),
            );

            $exitCode = $process->await($cancellation);

            $this->changeDetector->cancel($id);
        } while ($changed);

        return $exitCode;
    }
}
