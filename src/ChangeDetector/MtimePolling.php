<?php

declare(strict_types=1);

namespace Thesis\HotReload\ChangeDetector;

use Revolt\EventLoop;
use Thesis\HotReload\ChangeDetector;
use Thesis\HotReload\Target;

/**
 * @api
 */
final readonly class MtimePolling implements ChangeDetector
{
    /**
     * @param float $interval in seconds
     */
    public function __construct(
        private float $interval = 0.1,
    ) {}

    public function onChanged(Target $target, callable $listener): string
    {
        $snapshot = self::snapshot($target);

        return EventLoop::repeat($this->interval, static function (string $id) use ($target, &$snapshot, $listener): void {
            $newSnapshot = self::snapshot($target);

            if ($newSnapshot === $snapshot) {
                return;
            }

            $snapshot = $newSnapshot;

            $listener($id);
        });
    }

    public function cancel(string $id): void
    {
        EventLoop::cancel($id);
    }

    /**
     * @return non-empty-string
     */
    private static function snapshot(Target $target): string
    {
        $snapshot = [];

        foreach (new Finder($target) as $file) {
            \assert($file->getRealPath() !== false);

            $snapshot[$file->getRealPath()] ??= $file->getMTime();
        }

        ksort($snapshot);

        return hash('xxh128', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
