<?php

declare(strict_types=1);

namespace Thesis\HotReload;

/**
 * @api
 *
 * @implements \IteratorAggregate<int, \SplFileInfo>
 */
final readonly class Files implements \IteratorAggregate
{
    /**
     * @param non-empty-list<string> $paths
     * @param ?non-empty-list<string> $extensions
     * @param list<string> $excludes
     */
    public function __construct(
        public array $paths,
        public ?array $extensions = null,
        public array $excludes = [],
    ) {}

    public function getIterator(): \Traversable
    {
        foreach ($this->paths as $path) {
            $files = new \CallbackFilterIterator(
                new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                ),
                $this->matches(...),
            );

            foreach ($files as $file) {
                \assert($file instanceof \SplFileInfo);

                yield $file;
            }
        }
    }

    /**
     * Matches against extensions and excludes only, not paths.
     */
    public function matches(\SplFileInfo $file): bool
    {
        if (!$file->isFile()) {
            return false;
        }

        if (array_any($this->excludes, static fn($exclude) => fnmatch($exclude, $file->getPathname(), FNM_PATHNAME))) {
            return false;
        }

        if ($this->extensions === null) {
            return true;
        }

        return \in_array($file->getExtension(), $this->extensions, true);
    }
}
