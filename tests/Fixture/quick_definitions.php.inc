<?php

test('expands quick definitions', function (): void {
    $fields = Mockery::mock(FieldsInterface::class, [
        'getVersion' => $version,
        'getVariant' => 2,
    ]);
    $this->provider = Mockery::mock(NodeProviderInterface::class, ['getNode' => $node]);
});

test('keeps quick definitions that another expectation overrides', function (): void {
    $plugins = Mockery::mock(Plugins::class, ['getAllPlugins' => []]);
    $plugins->shouldReceive('getAllPlugins')->andReturn([$plugin]);
});

test('keeps constructor arguments and inline quick definitions', function (): void {
    $partial = Mockery::mock(Passkeys::class, [$first, $second])->makePartial();
    $navigation = new Navigation(Mockery::mock(Plugins::class, ['getAllPlugins' => []]));
});

test('keeps pipe-separated targets', function (): void {
    $either = Mockery::mock('App\First|App\Second');
});

test('maps andReturns', function (): void {
    $repository = Mockery::mock(Repository::class);
    $repository->shouldReceive('all')->andReturns([]);
});

-----
<?php

test('expands quick definitions', function (): void {
    $fields = \JMac\Testing\Double::for(FieldsInterface::class)->strict();
    $fields->allows('getVersion')->returns($version);
    $fields->allows('getVariant')->returns(2);
    $this->provider = \JMac\Testing\Double::for(NodeProviderInterface::class)->strict();
    $this->provider->allows('getNode')->returns($node);
});

test('keeps quick definitions that another expectation overrides', function (): void {
    $plugins = Mockery::mock(Plugins::class, ['getAllPlugins' => []]);
    $plugins->shouldReceive('getAllPlugins')->andReturn([$plugin]);
});

test('keeps constructor arguments and inline quick definitions', function (): void {
    $partial = Mockery::mock(Passkeys::class, [$first, $second])->makePartial();
    $navigation = new Navigation(Mockery::mock(Plugins::class, ['getAllPlugins' => []]));
});

test('keeps pipe-separated targets', function (): void {
    $either = Mockery::mock('App\First|App\Second');
});

test('maps andReturns', function (): void {
    $repository = \JMac\Testing\Double::for(Repository::class)->strict();
    $repository->allows('all')->returns([]);
});
