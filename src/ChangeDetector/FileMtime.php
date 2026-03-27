<?php

declare(strict_types=1);

namespace Thesis\HotReload\ChangeDetector;

use Thesis\HotReload\ChangeDetector;
use Thesis\HotReload\Target;

final readonly class FileMtime implements ChangeDetector
{
    public function createDetector(Target $target): callable
    {
        /** @var string */
        $snapshot = $this->snapshot($target);

        return function () use ($target, &$snapshot): bool {
            $newSnapshot = $this->snapshot($target);

            if ($newSnapshot === $snapshot) {
                return false;
            }

            $snapshot = $newSnapshot;

            return true;
        };
    }

    private function snapshot(Target $target): string
    {
        $snapshot = [];

        foreach (new Finder($target) as $file) {
            \assert($file->getRealPath() !== false);

            $snapshot[$file->getRealPath()] = $file->getMTime();
        }

        ksort($snapshot);

        return hash('xxh128', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
