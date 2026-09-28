<?php

declare(strict_types=1);

use MockeryToDouble\Rector\Rector\MockeryDoubleOwnershipRector;
use MockeryToDouble\Rector\Rector\MockeryExpectationRector;
use MockeryToDouble\Rector\Rector\MockeryQuickDefinitionRector;
use MockeryToDouble\Rector\Rector\MockeryStaticCallRector;
use MockeryToDouble\Rector\Rector\SequentialMockeryExpectationRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withRules([
        MockeryQuickDefinitionRector::class,
        SequentialMockeryExpectationRector::class,
        MockeryDoubleOwnershipRector::class,
        MockeryStaticCallRector::class,
        MockeryExpectationRector::class,
    ]);
