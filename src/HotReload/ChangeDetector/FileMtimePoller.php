<?php

declare(strict_types=1);

namespace Thesis\HotReload\ChangeDetector;

use Revolt\EventLoop;
use Thesis\HotReload\ChangeDetector;
use Thesis\HotReload\Files;

/**
 * @api
 */
final readonly class FileMtimePoller implements ChangeDetector
{
    public const float DEFAULT_INTERVAL = 0.1;

    /**
     * @param float $interval in seconds
     */
    public function __construct(
        private float $interval = self::DEFAULT_INTERVAL,
    ) {}

    public function onChanged(Files $files, callable $listener): string
    {
        $snapshot = self::snapshot($files);

        return EventLoop::repeat($this->interval, static function (string $id) use ($files, &$snapshot, $listener): void {
            $newSnapshot = self::snapshot($files);

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
    private static function snapshot(Files $files): string
    {
        $snapshot = [];

        foreach ($files as $file) {
            $realPath = $file->getRealPath();

            if ($realPath === false) {
                throw new \RuntimeException("File {$file} does not exist");
            }

            $snapshot[$realPath] ??= $file->getMTime();
        }

        ksort($snapshot);

        return hash('xxh128', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
