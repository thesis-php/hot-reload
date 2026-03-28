<?php

declare(strict_types=1);

namespace Thesis\HotReload;

/**
 * @api
 */
interface ChangeDetector
{
    /**
     * @param callable(string): void $listener
     */
    public function onChanged(Files $files, callable $listener): string;

    public function cancel(string $id): void;
}
