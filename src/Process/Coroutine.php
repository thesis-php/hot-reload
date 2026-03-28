<?php

declare(strict_types=1);

namespace Thesis\HotReload\Process;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\DeferredCancellation;
use Amp\Future;
use Amp\NullCancellation;
use Thesis\HotReload\Process;
use function Amp\async;

/**
 * @api
 */
final readonly class Coroutine implements Process
{
    private DeferredCancellation $cancel;

    /**
     * @var Future<non-negative-int>
     */
    private Future $future;

    /**
     * @param \Closure(Cancellation): non-negative-int $coroutine
     */
    public function __construct(\Closure $coroutine)
    {
        $this->cancel = new DeferredCancellation();

        $cancellation = $this->cancel->getCancellation();

        /** @var Future<non-negative-int> */
        $future = async(static function () use ($coroutine, $cancellation): int {
            try {
                return ($coroutine)($cancellation);
            } catch (CancelledException) {
                return 0;
            }
        });

        $this->future = $future;
    }

    public function terminate(): void
    {
        $this->cancel->cancel();
    }

    public function await(Cancellation $cancellation = new NullCancellation()): int
    {
        return $this->future->await($cancellation);
    }
}
