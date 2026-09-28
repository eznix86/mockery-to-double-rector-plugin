<?php

declare(strict_types=1);

use MockeryToDouble\Rector\Rector\MockeryDoubleOwnershipRector;
use MockeryToDouble\Rector\Rector\MockeryExpectationRector;
use MockeryToDouble\Rector\Rector\MockeryQuickDefinitionRector;
use MockeryToDouble\Rector\Rector\MockeryStaticCallRector;
use MockeryToDouble\Rector\Rector\SequentialMockeryExpectationRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(MockeryQuickDefinitionRector::class);
    $rectorConfig->rule(SequentialMockeryExpectationRector::class);
    $rectorConfig->rule(MockeryDoubleOwnershipRector::class);
    $rectorConfig->rule(MockeryStaticCallRector::class);
    $rectorConfig->rule(MockeryExpectationRector::class);
};
