<?php

declare(strict_types=1);

namespace Thesis\HotReload\Internal;

use Revolt\EventLoop;

/**
 * @internal
 *
 * @param callable(): void $callback
 * @param float $delay in seconds
 * @return callable(): void
 */
function debounce(callable $callback, float $delay = 0.1): callable
{
    if ($delay < 0) {
        throw new \Error('Delay must be greater than or equal to zero');
    }

    if ($delay === 0.0) {
        return $callback;
    }

    return static function () use ($callback, $delay): void {
        /** @var ?string */
        static $timerId = null;

        if ($timerId !== null) {
            EventLoop::cancel($timerId);
        }

        $timerId = EventLoop::delay($delay, static function () use ($callback, &$timerId): void {
            $timerId = null;

            $callback();
        });
    };
}
