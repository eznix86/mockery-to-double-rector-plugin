<?php

$finder = Mockery::mock(Repository::class);
$finder->shouldReceive('find')->andReturn($first);
$finder->shouldReceive('find')->andReturn($second);

$saver = Mockery::mock(Repository::class);
$saver->shouldReceive('save')->with(1)->once()->andReturn(true);
$saver->shouldReceive('save')->with(2)->once()->andReturn(false);

-----
<?php

$finder = Mockery::mock(Repository::class);
$finder->shouldReceive('find')->andReturn($first);
$finder->shouldReceive('find')->andReturn($second);

$saver = \JMac\Testing\Double::for(Repository::class)->strict();
$saver->expects('save')->with(1)->returns(true);
$saver->expects('save')->with(2)->returns(false);
