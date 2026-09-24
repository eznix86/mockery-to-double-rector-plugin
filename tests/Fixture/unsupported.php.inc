<?php

use Mockery;

$alias = Mockery::mock('alias:LegacyFacade');
$overload = Mockery::mock('overload:LegacyService');
$configuredDefault = $mock->shouldReceive('find')->andReturn(null)->byDefault();
$globalOrder = $mock->shouldReceive('first')->globally()->ordered();
$customLoose = Mockery::mock(stdClass::class)->shouldIgnoreMissing('fallback');
$duck = $mock->shouldReceive('quack')->with(Mockery::ducktype('swim'));
$many = $mock->shouldReceive('contains')->with(Mockery::contains('a', 'b'));

-----
<?php

use Mockery;

$alias = Mockery::mock('alias:LegacyFacade');
$overload = Mockery::mock('overload:LegacyService');
$configuredDefault = $mock->shouldReceive('find')->andReturn(null)->byDefault();
$globalOrder = $mock->shouldReceive('first')->globally()->ordered();
$customLoose = Mockery::mock(stdClass::class)->shouldIgnoreMissing('fallback');
$duck = $mock->shouldReceive('quack')->with(Mockery::ducktype('swim'));
$many = $mock->shouldReceive('contains')->with(Mockery::contains('a', 'b'));
