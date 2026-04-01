<?php

declare(strict_types=1);

namespace Thesis\HotReload\Internal;

/**
 * Marker exception to differentiate cancellation reason in hotReload().
 *
 * @internal
 */
final class FilesChanged extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Task terminated: files changed');
    }
}
