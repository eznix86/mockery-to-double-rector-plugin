<?php

beforeEach(function (): void {
    $this->cache = Mockery::mock(Cache::class);
    $this->cache->shouldReceive('get')->andReturn(1);
});

test('keeps expectations registered in callbacks and loops', function (): void {
    $channels = Mockery::mock(ChannelManager::class);
    collect($connections)->each(function ($connection) use ($channels) {
        $channels->shouldReceive('unsubscribeFromAll')->once()->with($connection);
    });

    $jobs = Mockery::mock(Queue::class);
    foreach ($payloads as $payload) {
        $jobs->shouldReceive('push')->once()->with($payload);
    }
});

test('keeps expectations that use a Mockery matcher variable', function (): void {
    $response = Mockery::type(ResponseInterface::class);
    $server = Mockery::mock(AuthorizationServer::class);
    $server->shouldReceive('respond')->with($request, $response)->andReturn($psrResponse);
});

test('keeps inline doubles of classes with reserved method names', function (): void {
    $tool = new SearchDocs(Mockery::mock(\Mockery\ExpectationDirector::class));
});

-----
<?php

beforeEach(function (): void {
    $this->cache = \JMac\Testing\Double::for(Cache::class);
    $this->cache->allows('get')->returns(1);
});

test('keeps expectations registered in callbacks and loops', function (): void {
    $channels = Mockery::mock(ChannelManager::class);
    collect($connections)->each(function ($connection) use ($channels) {
        $channels->shouldReceive('unsubscribeFromAll')->once()->with($connection);
    });

    $jobs = Mockery::mock(Queue::class);
    foreach ($payloads as $payload) {
        $jobs->shouldReceive('push')->once()->with($payload);
    }
});

test('keeps expectations that use a Mockery matcher variable', function (): void {
    $response = Mockery::type(ResponseInterface::class);
    $server = Mockery::mock(AuthorizationServer::class);
    $server->shouldReceive('respond')->with($request, $response)->andReturn($psrResponse);
});

test('keeps inline doubles of classes with reserved method names', function (): void {
    $tool = new SearchDocs(Mockery::mock(\Mockery\ExpectationDirector::class));
});
