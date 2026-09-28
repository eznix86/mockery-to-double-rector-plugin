# Mockery to Double Rector set

A Rector extension that converts the mechanically safe entries in [`jasonmccreary/double`](https://github.com/jasonmccreary/double)'s Mockery migration matrix.

## Getting started

### 1. Install the Rector set and Double

```bash
composer require --dev eznix86/mockery-to-double-rector jasonmccreary/double
```

### 2. Enable Double verification in Pest

Double must verify `expects()` after each test. Add its integration trait to your application's `tests/Pest.php`:

```php
<?php

use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;

uses(VerifiesDoubles::class)->in('Feature', 'Unit');
```

Adjust the directories passed to `in()` if your tests live elsewhere.

### 3. Add the set to `rector.php`

Create or update the `rector.php` file at the root of your project:

```php
<?php

declare(strict_types=1);

use MockeryToDouble\Rector\Set\DoubleSetList;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/tests',
    ])
    ->withSets([
        DoubleSetList::MOCKERY_TO_DOUBLE,
    ]);
```

If you already have a Rector configuration, add `DoubleSetList::MOCKERY_TO_DOUBLE` to its existing `withSets()` array.

### 4. Preview the migration

```bash
vendor/bin/rector process --dry-run
```

Review the diff, especially any Mockery constructs listed under [Intentionally not auto-converted](#intentionally-not-auto-converted).

### 5. Apply and test

```bash
vendor/bin/rector process
vendor/bin/pest
```

Once the migrated suite passes, remove obsolete `Mockery::close()` calls and check for Mockery code that needs manual migration:

```bash
rg 'Mockery|shouldReceive|shouldHaveReceived|byDefault|globally' tests
```

## Implemented mappings

| Mockery | Double |
|---|---|
| `Mockery::mock(Foo::class)` | `Double::for(Foo::class)->strict()` |
| `Mockery::mock(Foo::class.','.Bar::class)` or `'Foo, Bar'` | `Double::for(Foo::class, Bar::class)->strict()` |
| `Mockery::spy(Foo::class)` | `Double::for(Foo::class)` (loose mode) |
| `shouldReceive()` | `allows()` |
| `shouldReceive()->once()` | `expects()` |
| `shouldReceive()->twice()` | `expects()->times(2)` |
| `shouldReceive()->times(n)` for literal `n > 0` | `expects()->times(n)` |
| `shouldHaveReceived()` | `received()` |
| `shouldNotReceive('method')` | `expects('method')->never()` |
| `shouldNotHaveReceived()` | `received()->never()` |
| `andReturn()` | `returns()` |
| `andReturnUsing()` | `resolves()` |
| `andReturnTrue()` / `andReturnFalse()` / `andReturnNull()` | `returns(true)` / `returns(false)` / `returns(null)` |
| `$mock->shouldReceive()->andReturnSelf()` | `$mock->allows()->returns($mock)` |
| `andThrow($throwable)` | `throws($throwable)` |
| `andThrow(Exception::class, ...)` | `throws(new Exception(...))` |
| `once()` | `times(1)` |
| `twice()` | `times(2)` |
| `atLeast()->times(n)` | `times(minimum: n)` |
| `atMost()->times(n)` | `times(maximum: n)` |
| `between(min, max)` | `times(min, max)` |
| `withNoArgs()` | `with()` |
| `withAnyArgs()` | removed because an expectation without `with()` matches any arguments |
| `withArgs($callback)` | `with(Argument::all($callback))` |
| `makePartial()` / `shouldDeferMissing()` | `passthru()` when no constructor conversion is required |
| `shouldIgnoreMissing()` | removed because loose mode is Double's default |
| `Mockery::any()` | `Argument::any()` |
| `Mockery::type()` | `Argument::type()` |
| `on()` / `capture()` / `pattern()` | `satisfies()` / `capture()` / `matches()` |
| `anyOf()` / `notAnyOf()` / `not()` | `any()` / `not()->any()` / `not()` |
| `contains()` / `hasKey()` / `hasValue()` | equivalent `Argument::contains()` matcher |
| `isSame()` | `Argument::same()` |
| `mustBe()` / `isEqual()` | the plain argument value |
| `andAnyOtherArgs()` | `Argument::remaining()` |

`shouldReceive()` intentionally maps to `allows()`: a bare Mockery expectation does not require a call count, while Double's `expects()` requires one call by default. A positive, explicit count promotes the root to `expects()`; because one call is its default, `once()` is removed while larger counts use `times(...)`. Zero and dynamic counts stay rooted at `allows()` to avoid imposing Double's required-expectation behavior.

## Semantic safeguards

- **Whole doubles only:** a double converts only when its Mockery factory is visible and every expectation on it has a Double equivalent. The factory must be a local variable in the same test, or a `$this->property` assigned anywhere in the file. If one part cannot convert, the factory, its expectations and their matchers all stay Mockery, so a test never mixes the two libraries. Receivers whose factory is not visible stay Mockery. Examples are `$this->mock(Foo::class, fn ($mock) => ...)` closures, method parameters, and properties set in a parent class.
- **Declared methods:** Double only configures methods that its target declares. When Rector can load the target class, a double whose expectations name a method that no target declares stays Mockery. This covers magic `__call()` methods and methods missing from an interface, such as `hasVerifiedEmail()` on `Authenticatable`. Run Rector from the project so it uses the project's autoloader. When a class cannot be loaded, this check is skipped.
- **Demeter chains:** `shouldReceive('tokens->where->pluck')` has no Double equivalent, so its double stays Mockery.
- **Modes:** plain Mockery mocks become strict doubles. Spies and `shouldIgnoreMissing()` use Double's loose default. `makePartial()` and argument-free `shouldDeferMissing()` become `passthru()`. Constructor-argument passthroughs remain unchanged because Double needs a real instance rather than Mockery's constructor array.
- **Ordering:** per-mock `ordered()` remains valid and is retained. Chains containing `globally()` remain unchanged because Double has no cross-double global order.
- **Sequential answers:** consecutive statements of the form `$mock->shouldReceive('m')->once()->andReturn($value)`, with the same `with(...)` arguments, merge into one `expects('m')->times(n)->returns(...)` chain. The answers keep their order. This applies only when no other expectation for that method exists in the same test.
- **Repeated answers:** other repeated expectations with the same receiver, method, and simple `with(...)` signature in the same test keep their double on Mockery for manual consolidation. Distinct argument-specific expectations are still converted. Expectations in different tests do not count as repeated, even when they share a variable name.
- **Negative expectations:** `shouldNotReceive()` counts as a repeated expectation. Double reports a `never()` expectation and a counted expectation with the same signature as ambiguous, so both remain unchanged.
- **`andReturnSelf()`:** this converts only when the receiver is a variable or a property of a variable, so `returns(...)` can name it again. An inline `Mockery::mock(...)->shouldReceive(...)->andReturnSelf()` remains unchanged.
- **Targets:** comma-separated targets become separate `Double::for()` arguments. Partial-mock targets such as `'Foo[bar]'`, and dynamic strings that cannot be split, remain unchanged.
- **Features that did not carry over:** aliases, static mocks, `ducktype()`, `byDefault()` and `shouldAllowMockingProtectedMethods()` keep their double on Mockery.
- **`shouldNotHaveBeenCalled()`:** this remains unchanged. Despite its name, Mockery only checks invocation of the mock itself, whereas Double's `unused()` checks every method call; converting it automatically would strengthen the assertion and could change a test's meaning.

## Development

The development setup uses Pest tests, Pest-aware PHPStan at maximum level, Rector, and Pint. Composer exposes the complete workflow:

```bash
composer format # apply Rector and Pint
composer lint   # check Rector and Pint
composer test:types # run max-level PHPStan
composer test:unit  # run the Pest fixture suite
composer test       # run lint, PHPStan, and Pest
composer check      # alias for the complete test pipeline
```

## Intentionally not auto-converted

The set leaves constructs alone when a blind rewrite could change behavior or produce invalid Double code. Examples include:

- `Mockery::mock('alias:...')` and `Mockery::mock('overload:...')`
- bare, untyped `Mockery::mock()` calls
- mocks with constructor-argument arrays that require constructing a real passthrough instance
- `alias:` / `overload:` and static method mocking
- `Mockery::mock('Foo[method]')` partial-mock targets and dynamic target strings
- `shouldNotReceive()` with several method names
- `globally()` and global cross-double ordering
- `byDefault()` where Mockery's expectation-eviction behavior matters
- `ducktype()` and magic `__call()` methods
- multi-value `Mockery::contains(...)`, whose semantics do not match Double's single-needle matcher
- reserved Double method collisions, which require `override: true` and usually call-site changes
- repeated identical expectations that are not consecutive, or that use answers other than a single `andReturn()` value
- `withSomeOfArgs()`, which has no Double equivalent
- `->getMock()` chains on bare `Mockery::mock()` calls
- `shouldNotHaveBeenCalled()`, because `unused()` has intentionally stronger semantics
- `Mockery::close()`, until the application enables `VerifiesDoubles`
- Mockery doubles created by Laravel's `$this->mock()`, `$this->partialMock()` and `$this->spy()` helpers

Those cases should be migrated with dedicated rules once their exact Double equivalent is known for the target codebase.

## Rule organization

The rules run in this order:

1. A merge rule combines consecutive single-call expectations into one Mockery `times(n)->andReturn(...)` chain.
2. A repeated-expectation rule marks the remaining repeated expectations, so their return order is never reversed.
3. An ownership rule decides for each double whether it converts as a whole.
4. The factory rule and the expectation rule convert only what the ownership rule marked.
