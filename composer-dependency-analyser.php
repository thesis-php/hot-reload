<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return new Configuration()
    ->addPathToScan(__DIR__ . '/bin/hot-reload', isDev: false)
    ->ignoreErrorsOnExtension('ext-inotify', [
        ErrorType::DEV_DEPENDENCY_IN_PROD,
    ]);
