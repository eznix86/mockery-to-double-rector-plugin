<?php

use App\Contracts\Repository;
use Mockery;

$strict = Mockery::mock(Repository::class);
$partial = Mockery::mock(Repository::class)->makePartial();
$deferred = Mockery::mock(Repository::class)->shouldDeferMissing();
$loose = Mockery::mock(Repository::class)->shouldIgnoreMissing();

$repository->shouldReceive('fail')->andThrow(new RuntimeException('nope'));
$repository->shouldReceive('failClass')->andThrow(RuntimeException::class, 'nope', 10);
$repository->shouldReceive('failString')->andThrow('RuntimeException', 'nope');
$repository->shouldReceive('minimum')->atLeast()->times(2);
$repository->shouldReceive('maximum')->atMost()->times(4);
$repository->shouldReceive('range')->between(2, 5)->ordered();
$repository->shouldReceive('empty')->withNoArgs();
$repository->shouldReceive('arguments')->withArgs(fn ($first, $second) => $first < $second);

$repository->shouldHaveReceived('saved')->once();
$repository->shouldHaveReceived('savedOften')->twice();
$repository->shouldNotHaveReceived('deleted');
$repository->shouldHaveReceived('legacy', [1, 2]);
$repository->shouldNotHaveReceived('missing', [3]);
$repository->shouldNotHaveBeenCalled();

$repository->shouldReceive('match')->with(
    Mockery::on(fn ($value) => $value > 1),
    Mockery::capture($captured),
    Mockery::pattern('/foo/'),
    Mockery::anyOf('a', 'b'),
    Mockery::notAnyOf('c', 'd'),
    Mockery::not('e'),
    Mockery::contains('needle'),
    Mockery::hasKey('id'),
    Mockery::hasValue('value'),
    Mockery::isSame($object),
    Mockery::mustBe('literal'),
    Mockery::isEqual('equal'),
    Mockery::andAnyOtherArgs(),
);

-----
<?php

use App\Contracts\Repository;
use Mockery;

$strict = \JMac\Testing\Double::for(Repository::class)->strict();
$partial = \JMac\Testing\Double::for(Repository::class)->passthru();
$deferred = \JMac\Testing\Double::for(Repository::class)->passthru();
$loose = \JMac\Testing\Double::for(Repository::class);

$repository->allows('fail')->throws(new RuntimeException('nope'));
$repository->allows('failClass')->throws(new RuntimeException('nope', 10));
$repository->allows('failString')->throws(new \RuntimeException('nope'));
$repository->expects('minimum')->times(minimum: 2);
$repository->allows('maximum')->times(maximum: 4);
$repository->expects('range')->times(2, 5)->ordered();
$repository->allows('empty')->with();
$repository->allows('arguments')->with(\JMac\Testing\Matching\Argument::all(fn ($first, $second) => $first < $second));

$repository->received('saved')->times(1);
$repository->received('savedOften')->times(2);
$repository->received('deleted')->never();
$repository->received('legacy')->with(1, 2);
$repository->received('missing')->with(3)->never();
$repository->shouldNotHaveBeenCalled();

$repository->allows('match')->with(
    \JMac\Testing\Matching\Argument::satisfies(fn ($value) => $value > 1),
    \JMac\Testing\Matching\Argument::capture($captured),
    \JMac\Testing\Matching\Argument::matches('/foo/'),
    \JMac\Testing\Matching\Argument::any('a', 'b'),
    \JMac\Testing\Matching\Argument::not()->any('c', 'd'),
    \JMac\Testing\Matching\Argument::not('e'),
    \JMac\Testing\Matching\Argument::contains('needle'),
    \JMac\Testing\Matching\Argument::contains(static fn($value, $key) => $key === 'id'),
    \JMac\Testing\Matching\Argument::contains('value'),
    \JMac\Testing\Matching\Argument::same($object),
    'literal',
    'equal',
    \JMac\Testing\Matching\Argument::remaining(),
);
