<?php

use App\Contracts\Agent;
use App\Contracts\CanActAsTool;
use Mockery;

$concatenated = Mockery::mock(Agent::class.','.CanActAsTool::class);
$spaced = Mockery::mock(Agent::class.', '.CanActAsTool::class);
$literal = Mockery::spy('App\Contracts\Agent, App\Contracts\CanActAsTool');
$partial = Mockery::mock('App\Repository[find,save]');
$dynamic = Mockery::mock($prefix.'Agent');
$suffixed = Mockery::mock(Agent::class.'Stub');

-----
<?php

use App\Contracts\Agent;
use App\Contracts\CanActAsTool;
use Mockery;

$concatenated = \JMac\Testing\Double::for(Agent::class, CanActAsTool::class)->strict();
$spaced = \JMac\Testing\Double::for(Agent::class, CanActAsTool::class)->strict();
$literal = \JMac\Testing\Double::for('App\Contracts\Agent', 'App\Contracts\CanActAsTool');
$partial = Mockery::mock('App\Repository[find,save]');
$dynamic = Mockery::mock($prefix.'Agent');
$suffixed = Mockery::mock(Agent::class.'Stub');
