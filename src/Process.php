<?php

declare(strict_types=1);

namespace Thesis\HotReload;

use Amp\Cancellation;
use Amp\NullCancellation;

/**
 * @api
 */
interface Process
{
    public function terminate(): void;

    /**
     * @return non-negative-int Exit code
     */
    public function await(Cancellation $cancellation = new NullCancellation()): int;
}
