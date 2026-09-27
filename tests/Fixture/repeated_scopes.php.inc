<?php

final class GuardTest
{
    public function testGuest(): void
    {
        $guard->shouldReceive('guest')->andReturn(true);
    }

    public function testUser(): void
    {
        $guard->shouldReceive('guest')->andReturn(false);
        $callback = function () use ($guard): void {
            $guard->shouldReceive('guest')->andReturn(null);
        };
    }
}

test('guest', function (): void {
    $agent->shouldNotReceive('prompt');
});

test('prompt', function (): void {
    $agent->shouldReceive('prompt')->once()->andReturn($response);
});

-----
<?php

final class GuardTest
{
    public function testGuest(): void
    {
        $guard->allows('guest')->returns(true);
    }

    public function testUser(): void
    {
        $guard->shouldReceive('guest')->andReturn(false);
        $callback = function () use ($guard): void {
            $guard->shouldReceive('guest')->andReturn(null);
        };
    }
}

test('guest', function (): void {
    $agent->expects('prompt')->never();
});

test('prompt', function (): void {
    $agent->expects('prompt')->returns($response);
});
