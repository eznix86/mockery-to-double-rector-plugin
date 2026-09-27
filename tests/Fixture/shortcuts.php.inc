<?php

use App\Contracts\Repository;
use Mockery;

$repository->shouldReceive('exists')->andReturnTrue();
$repository->shouldReceive('save')->once()->with('draft')->andReturnFalse();
$repository->shouldReceive('find')->andReturnNull();

$builder->shouldReceive('where')->andReturnSelf();
$this->builder->shouldReceive('orderBy')->twice()->andReturnSelf();
$inline = Mockery::mock(Repository::class)->shouldReceive('fluent')->andReturnSelf();

$agent->shouldNotReceive('prompt');
$agent->shouldNotReceive('stream', 'prompt');

$provider->shouldNotReceive('name');
$provider->shouldReceive('name')->once()->andReturn('fake');

-----
<?php

use App\Contracts\Repository;
use Mockery;

$repository->allows('exists')->returns(true);
$repository->expects('save')->with('draft')->returns(false);
$repository->allows('find')->returns(null);

$builder->allows('where')->returns($builder);
$this->builder->expects('orderBy')->times(2)->returns($this->builder);
$inline = Mockery::mock(Repository::class)->shouldReceive('fluent')->andReturnSelf();

$agent->expects('prompt')->never();
$agent->shouldNotReceive('stream', 'prompt');

$provider->shouldNotReceive('name');
$provider->shouldReceive('name')->once()->andReturn('fake');
