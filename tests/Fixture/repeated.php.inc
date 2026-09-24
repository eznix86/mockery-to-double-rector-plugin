<?php

$repository->shouldReceive('find')->once()->andReturn($first);
$repository->shouldReceive('find')->once()->andReturn($second);

$repository->shouldReceive('save')->with(1)->once()->andReturn(true);
$repository->shouldReceive('save')->with(2)->once()->andReturn(false);

-----
<?php

$repository->shouldReceive('find')->once()->andReturn($first);
$repository->shouldReceive('find')->once()->andReturn($second);

$repository->expects('save')->with(1)->returns(true);
$repository->expects('save')->with(2)->returns(false);
