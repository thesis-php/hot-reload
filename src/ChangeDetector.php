<?php

declare(strict_types=1);

namespace Thesis\HotReload;

interface ChangeDetector
{
    /**
     * @return callable(): bool
     */
    public function createDetector(Target $target): callable;
}
