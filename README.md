# Mockery to Double

A [Rector](https://getrector.com) set that moves your test doubles from [Mockery](https://github.com/mockery/mockery) to [Double](https://github.com/jasonmccreary/double).

It converts what it can convert safely. It leaves the rest on Mockery, so a converted test never mixes the two libraries. The conversions follow Double's [migration guide](https://testdoublephp.com/migrating-from-mockery).

```php
// Before
$repository = Mockery::mock(BookRepository::class);
$repository->shouldReceive('find')->once()->with(123)->andReturn($book);

// After
$repository = Double::for(BookRepository::class);
$repository->expects('find')->with(123)->returns($book);
```

## Requirements

- PHP 8.4 or newer
- Rector 2.6 or newer

## Installation

```bash
composer require --dev eznix86/mockery-to-double-rector jasonmccreary/double
```

## Usage

### 1. Add the set to `rector.php`

```php
<?php

use MockeryToDouble\Rector\Set\DoubleSetList;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/tests'])
    ->withSets([DoubleSetList::MOCKERY_TO_DOUBLE]);
```

If you already have a `rector.php`, add `DoubleSetList::MOCKERY_TO_DOUBLE` to its `withSets()`.

### 2. Let Double verify your expectations

Double checks `expects()` at the end of each test through the `VerifiesDoubles` trait.

With Pest, add it in `tests/Pest.php`:

```php
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;

uses(VerifiesDoubles::class)->in('Feature', 'Unit');
```

With PHPUnit, add it to your base test case:

```php
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;

abstract class TestCase extends BaseTestCase
{
    use VerifiesDoubles;
}
```

### 3. Preview, apply, and test

```bash
vendor/bin/rector process --dry-run   # review the diff
vendor/bin/rector process             # apply it
vendor/bin/pest                       # or vendor/bin/phpunit
```

Run Rector from your project root. The rules load your classes to check which methods they declare.

## After the migration

Some tests keep Mockery. This is on purpose. [What stays on Mockery](#what-stays-on-mockery) lists each case and why. To find them:

```bash
grep -rnE 'Mockery|shouldReceive|shouldHaveReceived' tests
```

Some converted tests can fail. Double compares plain `with()` values with `===`. Mockery compares them with `==`. So `with(1)` no longer matches a call with `'1'`, and `with('name')` no longer matches a `Stringable`. Such a failure shows a type mismatch that the Mockery test did not check. Fix the test or the code. [Double's migration guide](https://testdoublephp.com/migrating-from-mockery) explains this in more detail.

When the suite passes, remove your `Mockery::close()` calls in the tests that no longer use Mockery.

## What converts

### Creating doubles

| Mockery | Double |
|---|---|
| `Mockery::mock(Foo::class)` | `Double::for(Foo::class)` |
| `Mockery::spy(Foo::class)` | `Double::for(Foo::class)` |
| `Mockery::mock(Foo::class.','.Bar::class)` | `Double::for(Foo::class, Bar::class)` |
| `Mockery::mock(Foo::class)->makePartial()` | `Double::for(Foo::class)->passthru()` |
| `->shouldIgnoreMissing()` | removed, Double is loose by default |
| `$mock = Mockery::mock(Foo::class, ['get' => 1])` | `$mock = Double::for(Foo::class);` then `$mock->allows('get')->returns(1);` |

### Expectations

| Mockery | Double |
|---|---|
| `shouldReceive('get')` | `allows('get')` |
| `shouldReceive('get')->once()` | `expects('get')` |
| `shouldReceive('get')->twice()` | `expects('get')->times(2)` |
| `shouldReceive('get')->times(3)` | `expects('get')->times(3)` |
| `shouldNotReceive('get')` | `expects('get')->never()` |
| `shouldHaveReceived('get')` | `received('get')` |
| `shouldNotHaveReceived('get')` | `received('get')->never()` |
| `shouldNotHaveBeenCalled()` | `unused()` |
| `shouldReceive('get')->andReturn(1)->byDefault()` | `allows('get')->returns(1)` |

A plain `shouldReceive()` becomes `allows()`, because Mockery does not require that call. A positive count makes it `expects()`.

A `byDefault()` expectation becomes a plain `allows()`. A later expectation for the same method still wins, as it did with Mockery.

`unused()` fails when the double received any call. Mockery's `shouldNotHaveBeenCalled()` only checked that the mock itself was not invoked, so a converted test can now fail where it passed before.

### Call counts

| Mockery | Double |
|---|---|
| `atLeast()->times(2)` | `times(minimum: 2)` |
| `atMost()->times(2)` | `times(maximum: 2)` |
| `between(2, 5)` | `times(2, 5)` |
| `zeroOrMoreTimes()` | removed, `allows()` accepts any count |

### Return values

| Mockery | Double |
|---|---|
| `andReturn($value)` / `andReturns($value)` | `returns($value)` |
| `andReturnTrue()` / `andReturnFalse()` / `andReturnNull()` | `returns(true)` / `returns(false)` / `returns(null)` |
| `$mock->shouldReceive('get')->andReturnSelf()` | `$mock->allows('get')->returns($mock)` |
| `andReturnUsing($callback)` | `resolves($callback)` |
| `andThrow($exception)` | `throws($exception)` |
| `andThrow(Foo::class, 'message')` | `throws(new Foo('message'))` |

Consecutive single-call expectations become one sequence, in the same order:

```php
// Before
$factory->shouldReceive('make')->once()->andReturn($first);
$factory->shouldReceive('make')->once()->andReturn($second);

// After
$factory->expects('make')->times(2)->returns($first, $second);
```

### Arguments

| Mockery | Double |
|---|---|
| `withNoArgs()` | `with()` |
| `withAnyArgs()` | removed, no `with()` matches any arguments |
| `withArgs($callback)` | `with(Argument::all($callback))` |
| `Mockery::any()` / `Mockery::type(Foo::class)` | `Argument::any()` / `Argument::type(Foo::class)` |
| `Mockery::on($callback)` | `Argument::satisfies($callback)` |
| `Mockery::capture($value)` / `Mockery::pattern($regex)` | `Argument::capture($value)` / `Argument::matches($regex)` |
| `Mockery::anyOf($a, $b)` / `Mockery::notAnyOf($a, $b)` | `Argument::any($a, $b)` / `Argument::not()->any($a, $b)` |
| `Mockery::not($value)` / `Mockery::isSame($value)` | `Argument::not($value)` / `Argument::same($value)` |
| `Mockery::contains($value)` / `hasValue($value)` / `hasKey($key)` | `Argument::contains(...)` |
| `Mockery::mustBe($value)` / `Mockery::isEqual($value)` | `$value` |
| `Mockery::andAnyOtherArgs()` | `Argument::remaining()` |

## What stays on Mockery

A double converts only as a whole: its factory, and every expectation on it. If one part has no safe Double equivalent, the whole double stays on Mockery.

### Double does not support it

| Case | Why |
|---|---|
| `alias:` and `overload:` mocks | Double has no static mocks. |
| `byDefault()` with a call count or with `with()` | Double keeps the count of every expectation. Mockery drops a default one when another one exists. |
| `globally()` | Double has no order across doubles. |
| `ducktype()`, `withSomeOfArgs()`, `Mockery::contains()` with several values | Double has no equivalent matcher. |
| `shouldReceive('tokens->where->pluck')` | Double has no chained expectations. |
| `shouldAllowMockingProtectedMethods()` | Double has no equivalent. |
| A method the class does not declare, for example one behind `__call()` | Double only doubles declared methods. |
| A class that declares `instance`, `expects`, `allows`, `strict`, `passthru`, `received`, `unused` or `verify`, for example `Illuminate\Http\Request` | These names collide with Double's own methods. |

### The rule cannot see enough

| Case | Why |
|---|---|
| `Mockery::mock()` without a class | Double needs a class. |
| `Mockery::mock(Foo::class, [$argument])->makePartial()` | Double needs a real instance, not constructor arguments. |
| `'Foo[method]'` and `'Foo\|Bar'` targets, or a target built at runtime | The rule cannot split them safely. |
| A mock from `$this->mock()`, a method parameter, or a parent class property | The rule cannot see where it was created. |
| A mock passed to `swap()`, `instance()`, `singleton()`, `bind()` or `scoped()` | Laravel's facades and container expect a Mockery mock there. |
| A matcher stored in a variable, for example `$type = Mockery::type(Foo::class)` | The rule cannot follow the variable. |
| `getMock()` chains | They need a manual rewrite. |

### The answers would change

| Case | Why |
|---|---|
| The same expectation twice, when the two cannot merge into one sequence | Double answers the last one first, so the order would flip. |
| An expectation inside a loop or a callback such as `collect()->each()` | It registers once per run. Double reports this as ambiguous. |
| An expectation without `with()` before a specific one for the same method | Mockery takes the first match. Double takes the specific one. |
| `shouldNotReceive()` and `shouldReceive()` on the same method | Double reports this as ambiguous. |
| `Mockery::close()` | It is still needed until every double in the test is converted. |

## Development

```bash
composer test       # Rector, Pint, PHPStan and the Pest fixtures
composer format     # apply Rector and Pint
composer test:unit  # the Pest fixtures only
composer test:types # PHPStan at max level
```

Each rule is tested with fixtures in `tests/Fixture`. A fixture holds the input code, a `-----` line, then the expected output.

## License

MIT. See [LICENSE](LICENSE).
