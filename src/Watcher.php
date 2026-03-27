<?php

declare(strict_types=1);

namespace Thesis\HotReload;

use Thesis\HotReload\ChangeDetector\FileMtime;
use function Amp\ByteStream\getStderr;
use function Amp\delay;

final readonly class Watcher
{
    public function __construct(
        private ChangeDetector $changeDetector = new FileMtime(),
    ) {}

    /**
     * @param string|list<string> $command
     * @return non-negative-int
     */
    public function watch(string|array $command, Target $target): int
    {
        $process = ProcessWithTTY::start($command);

        $changed = $this->changeDetector->createDetector($target);

        while ($process->isRunning) {
            if ($changed()) {
                getStderr()->write("\n\033[33m  ➜ Files changed, reloading...\033[0m\n\n");

                $process->terminate();

                $process = ProcessWithTTY::start($command);

                continue;
            }

            delay(0.1);
        }

        return $process->terminate();
    }
}
