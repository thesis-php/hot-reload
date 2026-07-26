<?php

declare(strict_types=1);

namespace Thesis\HotReload\ChangeDetector;

use Revolt\EventLoop;
use Thesis\HotReload\ChangeDetector;
use Thesis\HotReload\Files;
use function Thesis\exceptionally;

/**
 * @api
 */
final readonly class InotifyChangeDetector implements ChangeDetector
{
    public function __construct(
        private int $mask = IN_MODIFY | IN_CLOSE_WRITE | IN_MOVE_SELF | IN_DELETE_SELF,
    ) {
        if (!\extension_loaded('inotify')) {
            throw new \RuntimeException('Extension "inotify" not loaded.');
        }
    }

    public function onChanged(Files $files, callable $listener): string
    {
        $inotify = exceptionally(inotify_init(...));
        stream_set_blocking($inotify, false);
        $wds = [];

        foreach ($files as $file) {
            $wds[] = exceptionally(fn() => inotify_add_watch($inotify, exceptionally($file->getRealPath(...)), $this->mask));
        }

        return EventLoop::onReadable($inotify, function (string $id) use ($inotify, $wds, $files, $listener): void {
            // Clear inotify events queue
            inotify_read($inotify);

            // Reinit watchers
            foreach ($wds as $wd) {
                @inotify_rm_watch($inotify, $wd);
            }

            $wds = [];

            foreach ($files as $file) {
                $wds[] = inotify_add_watch($inotify, exceptionally($file->getRealPath(...)), $this->mask);
            }

            $listener($id);
        });
    }

    public function cancel(string $id): void
    {
        EventLoop::cancel($id);
    }
}
