<?php

final class GuardTest
{
    public function testGuest(): void
    {
        $guard = Mockery::mock(Guard::class);
        $guard->shouldReceive('guest')->andReturn(true);
    }

    public function testUser(): void
    {
        $guard = Mockery::mock(Guard::class);
        $guard->shouldReceive('guest')->andReturn(false);
        $callback = function () use ($guard): void {
            $guard->shouldReceive('guest')->andReturn(null);
        };
    }
}

test('guest', function (): void {
    $agent = Mockery::mock(Agent::class);
    $agent->shouldNotReceive('prompt');
});

test('prompt', function (): void {
    $agent = Mockery::mock(Agent::class);
    $agent->shouldReceive('prompt')->once()->andReturn($response);
});

describe('nested tests', function (): void {
    test('first', function (): void {
        $layout = Mockery::mock(FieldLayout::class);
        $layout->shouldReceive('getTabs')->andReturn([]);
    });

    test('second', function (): void {
        $layout = Mockery::mock(FieldLayout::class);
        $layout->shouldReceive('getTabs')->andReturn([]);
    });
});

-----
<?php

final class GuardTest
{
    public function testGuest(): void
    {
        $guard = \JMac\Testing\Double::for(Guard::class)->strict();
        $guard->allows('guest')->returns(true);
    }

    public function testUser(): void
    {
        $guard = Mockery::mock(Guard::class);
        $guard->shouldReceive('guest')->andReturn(false);
        $callback = function () use ($guard): void {
            $guard->shouldReceive('guest')->andReturn(null);
        };
    }
}

test('guest', function (): void {
    $agent = \JMac\Testing\Double::for(Agent::class)->strict();
    $agent->expects('prompt')->never();
});

test('prompt', function (): void {
    $agent = \JMac\Testing\Double::for(Agent::class)->strict();
    $agent->expects('prompt')->returns($response);
});

describe('nested tests', function (): void {
    test('first', function (): void {
        $layout = \JMac\Testing\Double::for(FieldLayout::class)->strict();
        $layout->allows('getTabs')->returns([]);
    });

    test('second', function (): void {
        $layout = \JMac\Testing\Double::for(FieldLayout::class)->strict();
        $layout->allows('getTabs')->returns([]);
    });
});
