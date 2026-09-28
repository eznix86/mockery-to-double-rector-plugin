<?php

final class OwnershipTest
{
    protected function setUp(): void
    {
        $this->herd = Mockery::mock(Herd::class);
        $this->config = Mockery::mock(Config::class);
    }

    public function testPropertyDoubleFromSetUp(): void
    {
        $this->herd->shouldReceive('isInstalled')->andReturn(true);
    }

    public function testByDefaultKeepsTheWholeDouble(): void
    {
        $this->config->shouldReceive('get')->andReturn(false)->byDefault();
        $this->config->shouldReceive('has')->andReturn(true);
    }

    public function testUntypedDouble(): void
    {
        $request = Mockery::mock();
        $request->shouldReceive('user')->andReturn($user = Mockery::mock(User::class));
        $user->shouldReceive('currentAccessToken')->andReturn($token = Mockery::mock());
        $token->shouldReceive('can')->with(Mockery::any())->andReturn(true);
    }

    public function testLaravelMockHelper(): void
    {
        $mock = Mockery::mock(Limiter::class);
        $mock->shouldReceive('hit')->once();

        $this->mock(Request::class, function ($mock) {
            $mock->shouldReceive('input')->with(Mockery::any())->andReturn('taylor');
        });
    }

    public function testConstructorPartial(): void
    {
        $assist = Mockery::mock(GuidelineAssist::class, [$this->project])->makePartial();
        $assist->shouldReceive('discover')->andReturn([]);
    }

    public function testProtectedMethods(): void
    {
        $command = Mockery::mock(UpdateCommand::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $command->shouldReceive('option')->andReturn(true);

        $assist = Mockery::mock(GuidelineAssist::class);
        $assist->shouldAllowMockingProtectedMethods();
        $assist->shouldReceive('discover')->andReturn([]);
    }

    public function testNativeMockeryExpectation(): void
    {
        $inertia = Mockery::mock(Inertia::class);
        $inertia->expects('render')->andReturn($response);

        $portable = Mockery::mock(Inertia::class);
        $portable->expects('render');
    }

    public function testArrowFunctionCapture(): void
    {
        $clock = Mockery::mock(Clock::class);
        $resolve = fn () => $clock->shouldReceive('now')->andReturn($now);
    }

    public function testMagicAndDemeterMethods(): void
    {
        $magic = Mockery::mock(\Mockery\Generator\Parameter::class);
        $magic->shouldReceive('getPosition')->andReturn(1);

        $declared = Mockery::mock(\Mockery\Generator\Parameter::class);
        $declared->shouldReceive('getName')->once();

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('tokens->where->pluck')->andReturn([]);
    }
}

-----
<?php

final class OwnershipTest
{
    protected function setUp(): void
    {
        $this->herd = \JMac\Testing\Double::for(Herd::class)->strict();
        $this->config = Mockery::mock(Config::class);
    }

    public function testPropertyDoubleFromSetUp(): void
    {
        $this->herd->allows('isInstalled')->returns(true);
    }

    public function testByDefaultKeepsTheWholeDouble(): void
    {
        $this->config->shouldReceive('get')->andReturn(false)->byDefault();
        $this->config->shouldReceive('has')->andReturn(true);
    }

    public function testUntypedDouble(): void
    {
        $request = Mockery::mock();
        $request->shouldReceive('user')->andReturn($user = \JMac\Testing\Double::for(User::class)->strict());
        $user->allows('currentAccessToken')->returns($token = Mockery::mock());
        $token->shouldReceive('can')->with(Mockery::any())->andReturn(true);
    }

    public function testLaravelMockHelper(): void
    {
        $mock = \JMac\Testing\Double::for(Limiter::class)->strict();
        $mock->expects('hit');

        $this->mock(Request::class, function ($mock) {
            $mock->shouldReceive('input')->with(Mockery::any())->andReturn('taylor');
        });
    }

    public function testConstructorPartial(): void
    {
        $assist = Mockery::mock(GuidelineAssist::class, [$this->project])->makePartial();
        $assist->shouldReceive('discover')->andReturn([]);
    }

    public function testProtectedMethods(): void
    {
        $command = Mockery::mock(UpdateCommand::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $command->shouldReceive('option')->andReturn(true);

        $assist = Mockery::mock(GuidelineAssist::class);
        $assist->shouldAllowMockingProtectedMethods();
        $assist->shouldReceive('discover')->andReturn([]);
    }

    public function testNativeMockeryExpectation(): void
    {
        $inertia = Mockery::mock(Inertia::class);
        $inertia->expects('render')->andReturn($response);

        $portable = \JMac\Testing\Double::for(Inertia::class)->strict();
        $portable->expects('render');
    }

    public function testArrowFunctionCapture(): void
    {
        $clock = \JMac\Testing\Double::for(Clock::class)->strict();
        $resolve = fn () => $clock->allows('now')->returns($now);
    }

    public function testMagicAndDemeterMethods(): void
    {
        $magic = Mockery::mock(\Mockery\Generator\Parameter::class);
        $magic->shouldReceive('getPosition')->andReturn(1);

        $declared = \JMac\Testing\Double::for(\Mockery\Generator\Parameter::class)->strict();
        $declared->expects('getName');

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('tokens->where->pluck')->andReturn([]);
    }
}
