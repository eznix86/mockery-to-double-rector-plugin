<?php

it('reconnects', function () {
    $clientFactory = Mockery::mock(RedisClientFactory::class);
    // Publisher client
    $clientFactory->shouldReceive('make')
        ->once()
        ->andReturn($publisher);

    // Subscriber client
    $clientFactory->shouldReceive('make')
        ->once()
        ->andReturn($subscriber);
});

it('keeps argument-specific answers in order', function () {
    $repository = Mockery::mock(Repository::class);
    $repository->shouldReceive('find')->with(1)->once()->andReturn($first);
    $repository->shouldReceive('find')->with(1)->once()->andReturn($second);
    $repository->shouldReceive('find')->with(1)->once()->andReturn($third);
});

it('leaves split sequences alone', function () {
    $repository = Mockery::mock(Repository::class);
    $repository->shouldReceive('find')->once()->andReturn($first);
    $other->doSomething();
    $repository->shouldReceive('find')->once()->andReturn($second);
});

it('leaves sequences with other answers alone', function () {
    $repository = Mockery::mock(Repository::class);
    $repository->shouldReceive('find')->once()->andReturnUsing($first);
    $repository->shouldReceive('find')->once()->andReturnUsing($second);
});

it('leaves sequences with a negative expectation alone', function () {
    $repository = Mockery::mock(Repository::class);
    $repository->shouldReceive('find')->once()->andReturn($first);
    $repository->shouldReceive('find')->once()->andReturn($second);
    $repository->shouldNotReceive('find');
});

it('drops withAnyArgs', function () {
    $clients = Mockery::mock(ClientRepository::class);
    $clients->shouldReceive('findActive')->withAnyArgs()->andReturn($client);
});

-----
<?php

it('reconnects', function () {
    $clientFactory = \JMac\Testing\Double::for(RedisClientFactory::class);
    // Publisher client
    // Subscriber client
    $clientFactory->expects('make')->times(2)->returns($publisher, $subscriber);
});

it('keeps argument-specific answers in order', function () {
    $repository = \JMac\Testing\Double::for(Repository::class);
    $repository->expects('find')->with(1)->times(3)->returns($first, $second, $third);
});

it('leaves split sequences alone', function () {
    $repository = Mockery::mock(Repository::class);
    $repository->shouldReceive('find')->once()->andReturn($first);
    $other->doSomething();
    $repository->shouldReceive('find')->once()->andReturn($second);
});

it('leaves sequences with other answers alone', function () {
    $repository = Mockery::mock(Repository::class);
    $repository->shouldReceive('find')->once()->andReturnUsing($first);
    $repository->shouldReceive('find')->once()->andReturnUsing($second);
});

it('leaves sequences with a negative expectation alone', function () {
    $repository = Mockery::mock(Repository::class);
    $repository->shouldReceive('find')->once()->andReturn($first);
    $repository->shouldReceive('find')->once()->andReturn($second);
    $repository->shouldNotReceive('find');
});

it('drops withAnyArgs', function () {
    $clients = \JMac\Testing\Double::for(ClientRepository::class);
    $clients->allows('findActive')->returns($client);
});
