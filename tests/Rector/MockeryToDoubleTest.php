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

it('migrates return shortcuts and negative expectations', function (): void {
    $this->doTestFile(__DIR__.'/../Fixture/shortcuts.php.inc');
});

it('splits comma-separated Mockery targets into separate Double targets', function (): void {
    $this->doTestFile(__DIR__.'/../Fixture/targets.php.inc');
});

it('detects repeated expectations per test, not per file', function (): void {
    $this->doTestFile(__DIR__.'/../Fixture/repeated_scopes.php.inc');
});

it('merges consecutive single-call expectations into one sequential answer', function (): void {
    $this->doTestFile(__DIR__.'/../Fixture/sequential.php.inc');
});

it('converts a double only when its factory and every expectation on it can convert', function (): void {
    $this->doTestFile(__DIR__.'/../Fixture/ownership.php.inc');
});

it('expands Mockery quick definitions into one expectation per method', function (): void {
    $this->doTestFile(__DIR__.'/../Fixture/quick_definitions.php.inc');
});
