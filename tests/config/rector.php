<?php

declare(strict_types=1);

use MockeryToDouble\Rector\Rector\MockeryExpectationRector;
use MockeryToDouble\Rector\Rector\MockeryStaticCallRector;
use MockeryToDouble\Rector\Rector\RepeatedMockeryExpectationRector;
use MockeryToDouble\Rector\Rector\SequentialMockeryExpectationRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withRules([
        SequentialMockeryExpectationRector::class,
        RepeatedMockeryExpectationRector::class,
        MockeryStaticCallRector::class,
        MockeryExpectationRector::class,
    ]);
