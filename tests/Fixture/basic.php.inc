<?php

use App\Contracts\Repository;
use Mockery;

$repository = Mockery::mock(Repository::class);
$repository->shouldReceive('find')->once()->with(123)->andReturn($book);
$repository->shouldReceive('save')->twice()->andReturn(true);
$repository->shouldReceive('delete')->times(3);
$repository->shouldReceive('archive')->times(0);
$repository->shouldReceive('all')->andReturn([]);

$spy = Mockery::spy(Repository::class);
$spy->shouldHaveReceived('store')->with(Mockery::type('array'));

-----
<?php

use App\Contracts\Repository;
use Mockery;

$repository = \JMac\Testing\Double::for(Repository::class)->strict();
$repository->expects('find')->with(123)->returns($book);
$repository->expects('save')->times(2)->returns(true);
$repository->expects('delete')->times(3);
$repository->allows('archive')->times(0);
$repository->allows('all')->returns([]);

$spy = \JMac\Testing\Double::for(Repository::class);
$spy->received('store')->with(\JMac\Testing\Matching\Argument::type('array'));
