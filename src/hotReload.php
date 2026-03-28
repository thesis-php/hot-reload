<?php

declare(strict_types=1);

namespace Thesis\HotReload;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\CompositeCancellation;
use Amp\DeferredCancellation;
use Amp\NullCancellation;
use Thesis\HotReload\ChangeDetector\FileMtimePoller;
use Thesis\HotReload\Internal\FilesChanged;
use function Amp\async;
use function Amp\ByteStream\getStderr;
use function Thesis\HotReload\Internal\debounce;

/**
 * @api
 *
 * @param string|non-empty-list<string>|Files $files
 * @param callable(Cancellation): void $process
 */
function hotReload(
    string|array|Files $files,
    callable $process,
    float $debounce = 0.1,
    Cancellation $cancellation = new NullCancellation(),
    ChangeDetector $changeDetector = new FileMtimePoller(),
): void {
    $files = match (true) {
        \is_string($files) => new Files([$files]),
        \is_array($files) => new Files($files),
        default => $files,
    };

    while (true) {
        $cancel = new DeferredCancellation();

        $run = async(static fn() => $process(new CompositeCancellation(
            $cancellation,
            $cancel->getCancellation(),
        )));

        $id = $changeDetector->onChanged(
            files: $files,
            listener: debounce(static function () use ($cancel): void {
                getStderr()->write("\n\033[33m  ➜ Files changed, reloading...\033[0m\n\n");

                $cancel->cancel(new FilesChanged());
            }, $debounce),
        );

        try {
            $run->await($cancellation);

            return;
        } catch (CancelledException $exception) {
            if ($exception->getPrevious() instanceof FilesChanged) {
                continue;
            }

            throw $exception;
        } finally {
            $changeDetector->cancel($id);
        }
    }
}
