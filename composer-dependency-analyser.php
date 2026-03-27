<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

return new Configuration()
    ->addPathToScan(__DIR__ . '/bin/hot-reload', isDev: false);
