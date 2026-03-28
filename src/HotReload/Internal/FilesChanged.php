<?php

declare(strict_types=1);

namespace Thesis\HotReload\Internal;

/**
 * Marker exception to differentiate cancellation.
 *
 * @internal
 */
final class FilesChanged extends \RuntimeException {}
