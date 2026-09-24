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
| `Mockery::spy(Foo::class)` | `Double::for(Foo::class)` (loose mode) |
| `shouldReceive()` | `allows()` |
| `shouldReceive()->once()` | `expects()` |
| `shouldReceive()->twice()` | `expects()->times(2)` |
| `shouldReceive()->times(n)` for literal `n > 0` | `expects()->times(n)` |
| `shouldHaveReceived()` | `received()` |
| `shouldNotHaveReceived()` | `received()->never()` |
| `andReturn()` | `returns()` |
| `andReturnUsing()` | `resolves()` |
| `andThrow($throwable)` | `throws($throwable)` |
| `andThrow(Exception::class, ...)` | `throws(new Exception(...))` |
| `once()` | `times(1)` |
| `twice()` | `times(2)` |
| `atLeast()->times(n)` | `times(minimum: n)` |
| `atMost()->times(n)` | `times(maximum: n)` |
| `between(min, max)` | `times(min, max)` |
| `withNoArgs()` | `with()` |
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

- **Modes:** plain Mockery mocks become strict doubles. Spies and `shouldIgnoreMissing()` use Double's loose default. `makePartial()` and argument-free `shouldDeferMissing()` become `passthru()`. Constructor-argument passthroughs remain unchanged because Double needs a real instance rather than Mockery's constructor array.
- **Ordering:** per-mock `ordered()` remains valid and is retained. Chains containing `globally()` remain unchanged because Double has no cross-double global order.
- **Repeated answers:** repeated expectations with the same receiver, method, and simple `with(...)` signature remain unchanged for manual consolidation into one `times(n)->returns(...)` chain. Distinct argument-specific expectations are still converted.
- **Features that did not carry over:** aliases, static mocks, `ducktype()`, and `byDefault()` chains remain unchanged.
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
- `globally()` and global cross-double ordering
- `byDefault()` where Mockery's expectation-eviction behavior matters
- `ducktype()` and magic `__call()` methods
- multi-value `Mockery::contains(...)`, whose semantics do not match Double's single-needle matcher
- reserved Double method collisions, which require `override: true` and usually call-site changes
- repeated identical expectations that need combining into one sequential `returns(...)` chain
- `shouldNotHaveBeenCalled()`, because `unused()` has intentionally stronger semantics
- `Mockery::close()`, until the application enables `VerifiesDoubles`

Those cases should be migrated with dedicated rules once their exact Double equivalent is known for the target codebase.

## Rule organization

The implementation keeps construction and matcher conversion separate from fluent expectation conversion. A pre-analysis rule protects repeated identical expectations from unsafe conversion so they can be consolidated manually without reversing their return order.
