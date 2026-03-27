<?php

declare(strict_types=1);

namespace Thesis\HotReload\ChangeDetector;

use Thesis\HotReload\Target;

/**
 * @implements \IteratorAggregate<int, \SplFileInfo>
 */
final readonly class Finder implements \IteratorAggregate
{
    public function __construct(
        private Target $target,
    ) {}

    public function getIterator(): \Traversable
    {
        foreach ($this->target->paths as $path) {
            $files = new \CallbackFilterIterator(
                new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                ),
                $this->filter(...),
            );

            foreach ($files as $file) {
                \assert($file instanceof \SplFileInfo);

                yield $file;
            }
        }
    }

    private function filter(\SplFileInfo $file): bool
    {
        if (!$file->isFile()) {
            return false;
        }

        foreach ($this->target->excludes as $exclude) {
            if (fnmatch($exclude, $file->getPathname(), FNM_PATHNAME)) {
                return false;
            }
        }

        if ($this->target->extensions === []) {
            return true;
        }

        return \in_array($file->getExtension(), $this->target->extensions, true);
    }
}
