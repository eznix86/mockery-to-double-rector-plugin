<?php

it('reconnects', function () {
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
    $repository->shouldReceive('find')->with(1)->once()->andReturn($first);
    $repository->shouldReceive('find')->with(1)->once()->andReturn($second);
    $repository->shouldReceive('find')->with(1)->once()->andReturn($third);
});

it('leaves split sequences alone', function () {
    $repository->shouldReceive('find')->once()->andReturn($first);
    $other->doSomething();
    $repository->shouldReceive('find')->once()->andReturn($second);
});

it('leaves sequences with other answers alone', function () {
    $repository->shouldReceive('find')->once()->andReturnUsing($first);
    $repository->shouldReceive('find')->once()->andReturnUsing($second);
});

it('leaves sequences with a negative expectation alone', function () {
    $repository->shouldReceive('find')->once()->andReturn($first);
    $repository->shouldReceive('find')->once()->andReturn($second);
    $repository->shouldNotReceive('find');
});

it('drops withAnyArgs', function () {
    $clients->shouldReceive('findActive')->withAnyArgs()->andReturn($client);
});

-----
<?php

it('reconnects', function () {
    // Publisher client
    // Subscriber client
    $clientFactory->expects('make')->times(2)->returns($publisher, $subscriber);
});

it('keeps argument-specific answers in order', function () {
    $repository->expects('find')->with(1)->times(3)->returns($first, $second, $third);
});

it('leaves split sequences alone', function () {
    $repository->shouldReceive('find')->once()->andReturn($first);
    $other->doSomething();
    $repository->shouldReceive('find')->once()->andReturn($second);
});

it('leaves sequences with other answers alone', function () {
    $repository->shouldReceive('find')->once()->andReturnUsing($first);
    $repository->shouldReceive('find')->once()->andReturnUsing($second);
});

it('leaves sequences with a negative expectation alone', function () {
    $repository->shouldReceive('find')->once()->andReturn($first);
    $repository->shouldReceive('find')->once()->andReturn($second);
    $repository->shouldNotReceive('find');
});

it('drops withAnyArgs', function () {
    $clients->allows('findActive')->returns($client);
});
