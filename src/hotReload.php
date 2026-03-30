<?php

declare(strict_types=1);

namespace Thesis;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\CompositeCancellation;
use Amp\DeferredCancellation;
use Amp\Future;
use Amp\NullCancellation;
use Thesis\HotReload\ChangeDetector;
use Thesis\HotReload\ChangeDetector\FileMtimePoller;
use Thesis\HotReload\Files;
use Thesis\HotReload\Internal\FilesChanged;
use function Amp\async;
use function Amp\ByteStream\getStderr;
use function Thesis\HotReload\Internal\debounce;

/**
 * @api
 *
 * @template T
 * @param string|non-empty-list<string>|Files $files
 * @param callable(Cancellation): T $process Cancellation is cancelled either when file changes are detected (process will be restarted)
 *                                           or when the outer $cancellation is triggered (process will not be restarted)
 * @return T
 */
function hotReload(
    string|array|Files $files,
    callable $process,
    float $debounce = 0.1,
    Cancellation $cancellation = new NullCancellation(),
    ChangeDetector $changeDetector = new FileMtimePoller(),
): mixed {
    $files = match (true) {
        \is_string($files) => new Files([$files]),
        \is_array($files) => new Files($files),
        default => $files,
    };

    while (true) {
        $cancel = new DeferredCancellation();

        /** @var Future<T> */
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
            return $run->await($cancellation);
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
