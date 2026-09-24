<?php

declare(strict_types=1);

use Pest\Rector\Set\PestSetList;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/config',
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withRootFiles()
    ->withSkip([
        __DIR__.'/tests/Fixture',
    ])
    ->withSets([
        PestSetList::CODING_STYLE,
    ]);
