<?php

declare(strict_types=1);

it('migrates supported Mockery calls to Double', function (): void {
    $this->doTestFile(__DIR__.'/../Fixture/basic.php.inc');
});

it('leaves unsupported Mockery doubles unchanged', function (): void {
    $this->doTestFile(__DIR__.'/../Fixture/unsupported.php.inc');
});

it('covers the safe entries in Double\'s Mockery migration matrix', function (): void {
    $this->doTestFile(__DIR__.'/../Fixture/matrix.php.inc');
});

it('preserves repeated identical expectations for manual sequence migration', function (): void {
    $this->doTestFile(__DIR__.'/../Fixture/repeated.php.inc');
});
