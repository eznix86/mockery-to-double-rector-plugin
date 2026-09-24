<?php

declare(strict_types=1);

namespace Tests;

use Rector\Testing\PHPUnit\AbstractRectorTestCase;

abstract class RectorTestCase extends AbstractRectorTestCase
{
    public function provideConfigFilePath(): string
    {
        return __DIR__.'/config/rector.php';
    }
}
