<?php

final class DefaultsTest
{
    protected function setUp(): void
    {
        $this->herd = Mockery::mock(Herd::class);
        $this->herd->shouldReceive('isInstalled')->andReturn(false)->byDefault();
        $this->sail = Mockery::mock(Sail::class);
        $this->sail->shouldReceive('isInstalled')->andReturn(false);
    }

    public function testOverridesTheDefault(): void
    {
        $this->herd->shouldReceive('isInstalled')->andReturn(true);
    }

    public function testOverridesTheDefaultAgain(): void
    {
        $this->herd->shouldReceive('isInstalled')->andReturn(true);
    }

    public function testRepeatsASetupExpectation(): void
    {
        $this->sail->shouldReceive('isInstalled')->andReturn(true);
    }

    public function testKeepsDefaultsWithACountOrArguments(): void
    {
        $counted = Mockery::mock(Cache::class);
        $counted->shouldReceive('get')->once()->andReturn(1)->byDefault();

        $specific = Mockery::mock(Cache::class);
        $specific->shouldReceive('get')->with('key')->andReturn(1)->byDefault();
    }

    public function testNothingWasCalled(): void
    {
        $logger = Mockery::spy(Logger::class);
        $logger->shouldNotHaveBeenCalled();
    }
}

-----
<?php

final class DefaultsTest
{
    protected function setUp(): void
    {
        $this->herd = \JMac\Testing\Double::for(Herd::class);
        $this->herd->allows('isInstalled')->returns(false);
        $this->sail = Mockery::mock(Sail::class);
        $this->sail->shouldReceive('isInstalled')->andReturn(false);
    }

    public function testOverridesTheDefault(): void
    {
        $this->herd->allows('isInstalled')->returns(true);
    }

    public function testOverridesTheDefaultAgain(): void
    {
        $this->herd->allows('isInstalled')->returns(true);
    }

    public function testRepeatsASetupExpectation(): void
    {
        $this->sail->shouldReceive('isInstalled')->andReturn(true);
    }

    public function testKeepsDefaultsWithACountOrArguments(): void
    {
        $counted = Mockery::mock(Cache::class);
        $counted->shouldReceive('get')->once()->andReturn(1)->byDefault();

        $specific = Mockery::mock(Cache::class);
        $specific->shouldReceive('get')->with('key')->andReturn(1)->byDefault();
    }

    public function testNothingWasCalled(): void
    {
        $logger = \JMac\Testing\Double::for(Logger::class);
        $logger->unused();
    }
}
