<?php

declare(strict_types=1);

namespace Thesis\HotReload;

final readonly class Target
{
    /**
     * @param list<string> $paths
     * @param list<string> $extensions
     * @param list<string> $excludes
     */
    public function __construct(
        public array $paths = [],
        public array $extensions = [],
        public array $excludes = [],
    ) {}
}
