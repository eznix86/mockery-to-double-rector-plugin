<?php

use App\Contracts\Repository;
use Mockery;

$repository = Mockery::mock(Repository::class);
$repository->shouldReceive('exists')->andReturnTrue();
$repository->shouldReceive('save')->once()->with('draft')->andReturnFalse();
$repository->shouldReceive('find')->andReturnNull();

$builder = Mockery::mock(Builder::class);
$builder->shouldReceive('where')->andReturnSelf();
$this->builder = Mockery::mock(Builder::class);
$this->builder->shouldReceive('orderBy')->twice()->andReturnSelf();
$inline = Mockery::mock(Repository::class)->shouldReceive('fluent')->andReturnSelf();

$agent = Mockery::mock(Agent::class);
$agent->shouldNotReceive('prompt');
$multiple = Mockery::mock(Agent::class);
$multiple->shouldNotReceive('stream', 'prompt');

$provider = Mockery::mock(Provider::class);
$provider->shouldNotReceive('name');
$provider->shouldReceive('name')->once()->andReturn('fake');

-----
<?php

use App\Contracts\Repository;
use Mockery;

$repository = \JMac\Testing\Double::for(Repository::class)->strict();
$repository->allows('exists')->returns(true);
$repository->expects('save')->with('draft')->returns(false);
$repository->allows('find')->returns(null);

$builder = \JMac\Testing\Double::for(Builder::class)->strict();
$builder->allows('where')->returns($builder);
$this->builder = \JMac\Testing\Double::for(Builder::class)->strict();
$this->builder->expects('orderBy')->times(2)->returns($this->builder);
$inline = Mockery::mock(Repository::class)->shouldReceive('fluent')->andReturnSelf();

$agent = \JMac\Testing\Double::for(Agent::class)->strict();
$agent->expects('prompt')->never();
$multiple = Mockery::mock(Agent::class);
$multiple->shouldNotReceive('stream', 'prompt');

$provider = Mockery::mock(Provider::class);
$provider->shouldNotReceive('name');
$provider->shouldReceive('name')->once()->andReturn('fake');
