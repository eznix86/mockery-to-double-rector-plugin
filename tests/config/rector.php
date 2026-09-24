<?php

declare(strict_types=1);

use MockeryToDouble\Rector\Rector\MockeryExpectationRector;
use MockeryToDouble\Rector\Rector\MockeryStaticCallRector;
use MockeryToDouble\Rector\Rector\RepeatedMockeryExpectationRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withRules([
        RepeatedMockeryExpectationRector::class,
        MockeryStaticCallRector::class,
        MockeryExpectationRector::class,
    ]);
