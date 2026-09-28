<?php

declare(strict_types=1);

namespace MockeryToDouble\Rector\Rector;

use MockeryToDouble\Rector\Support\BindingScope;
use MockeryToDouble\Rector\Support\MockeryFactory;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\While_;
use PhpParser\NodeFinder;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use Rector\PhpParser\Node\FileNode;
use Rector\Rector\AbstractRector;

/** Converts a Mockery double only when its factory and every expectation on it have a Double equivalent. */
final class MockeryDoubleOwnershipRector extends AbstractRector
{
    public const OWNED_ATTRIBUTE = 'mockery_to_double_owned';

    public const KEEP_ATTRIBUTE = 'mockery_to_double_keep';

    private const EXPECTATION_ROOTS = ['shouldReceive', 'shouldNotReceive'];

    private const RECEIVED_ROOTS = ['shouldHaveReceived', 'shouldNotHaveReceived'];

    private const DOUBLE_MODES = ['makePartial', 'shouldDeferMissing', 'shouldIgnoreMissing'];

    private const MOCKERY_API = [
        'allows', 'expects', 'byDefault', 'getMock', 'makePartial', 'shouldDeferMissing', 'shouldIgnoreMissing',
        'shouldAllowMockingProtectedMethods', 'shouldAllowMockingMethod', 'shouldHaveBeenCalled',
    ];

    private const EXPECTATION_METHODS = [
        'with', 'withNoArgs', 'withAnyArgs', 'withArgs', 'andReturn', 'andReturns', 'andReturnUsing', 'andReturnTrue', 'andReturnFalse',
        'andReturnNull', 'andReturnSelf', 'andThrow', 'andThrows', 'once', 'twice', 'times', 'atLeast', 'atMost', 'between',
        'never', 'ordered', 'zeroOrMoreTimes', 'byDefault',
    ];

    private const RECEIVED_METHODS = ['with', 'withNoArgs', 'withAnyArgs', 'withArgs', 'once', 'twice', 'times', 'never'];

    private const MATCHERS = [
        'any', 'anyOf', 'type', 'on', 'capture', 'pattern', 'not', 'notAnyOf', 'contains', 'hasValue', 'hasKey', 'isSame',
        'mustBe', 'isEqual', 'andAnyOtherArgs', 'andAnyOthers', 'mock', 'spy',
    ];

    private const DOUBLE_RESERVED_METHODS = ['instance', 'expects', 'allows', 'strict', 'passthru', 'received', 'unused', 'verify'];

    private const REGISTRATIONS = ['swap', 'instance', 'singleton', 'bind', 'scoped'];

    private const PEST_FUNCTIONS = ['test', 'it', 'describe', 'beforeEach', 'afterEach', 'beforeAll', 'afterAll'];

    private const PEST_SETUP_FUNCTIONS = ['beforeEach', 'beforeAll'];

    private const SETUP_METHODS = ['setup', 'setupbeforeclass'];

    private const COUNT_METHODS = ['once', 'twice', 'times', 'atLeast', 'atMost', 'between', 'never'];

    private int $scopeCount = 0;

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {}

    /** @var array<string, list<StaticCall>> */
    private array $factories = [];

    /** @var array<string, list<MethodCall>> */
    private array $chains = [];

    /** @var array<string, true> */
    private array $disqualified = [];

    /** @var array<int, true> */
    private array $seen = [];

    /** @var array<string, array<string, list<string>>> */
    private array $signatures = [];

    /** @var array<string, true> */
    private array $matchers = [];

    /** @var array<int, true> */
    private array $callbacks = [];

    private bool $repeating = false;

    private int $unitCount = 0;

    private string $unit = 'file';

    /** @var array<int, string> */
    private array $pestUnits = [];

    /** @var array<string, true> */
    private array $setupUnits = [];

    /** @var array<string, array<string, list<array{string, string}>>> */
    private array $propertyExpectations = [];

    /** @return array<class-string<Node>> */
    public function getNodeTypes(): array
    {
        return [FileNode::class];
    }

    /** @param FileNode $node */
    public function refactor(Node $node): null
    {
        $this->factories = [];
        $this->chains = [];
        $this->disqualified = [];
        $this->seen = [];
        $this->signatures = [];
        $this->matchers = [];
        $this->callbacks = [];
        $this->repeating = false;
        $this->unit = 'file';
        $this->pestUnits = [];
        $this->setupUnits = [];
        $this->propertyExpectations = [];

        $this->walk($node->stmts, $this->newScope());
        $this->disqualifyRepeatedPropertyExpectations();

        foreach ($this->factories as $binding => $factories) {
            $owned = ! isset($this->disqualified[$binding])
                && array_filter($factories, static fn (StaticCall $factory): bool => ! MockeryFactory::isConvertible($factory)) === []
                && $this->fitsDouble($factories, $this->chains[$binding] ?? []);

            foreach ($owned ? $this->chains[$binding] ?? [] : [] as $chain) {
                $this->markOwned($chain);
            }

            foreach ($owned ? [] : $factories as $factory) {
                $factory->setAttribute(self::KEEP_ATTRIBUTE, true);
            }
        }

        return null;
    }

    /** @param Node|array<mixed> $node */
    private function walk(Node|array $node, BindingScope $scope): void
    {
        if (is_array($node)) {
            foreach ($node as $child) {
                if ($child instanceof Node || is_array($child)) {
                    $this->walk($child, $scope);
                }
            }

            return;
        }

        if ($node instanceof ClassMethod || $node instanceof Function_) {
            $unit = $this->enterUnit($node instanceof ClassMethod && in_array($node->name->toLowerString(), self::SETUP_METHODS, true));
            $this->walk($node->stmts ?? [], $this->declareParams($node->params, $this->newScope()));
            $this->unit = $unit;

            return;
        }

        if (($node instanceof Closure || $node instanceof ArrowFunction) && isset($this->pestUnits[spl_object_id($node)])) {
            $kind = $this->pestUnits[spl_object_id($node)];
            unset($this->pestUnits[spl_object_id($node)]);
            $unit = $kind === 'describe' ? $this->unit : $this->enterUnit($kind === 'setup');
            $this->walk($node, $scope);
            $this->unit = $unit;

            return;
        }

        if ($node instanceof For_ || $node instanceof Foreach_ || $node instanceof While_ || $node instanceof Do_) {
            $this->walkRepeating($node, $scope);

            return;
        }

        if (($node instanceof Closure || $node instanceof ArrowFunction) && isset($this->callbacks[spl_object_id($node)]) && ! $this->repeating) {
            $this->walkRepeating($node, $scope);

            return;
        }

        if ($node instanceof Closure) {
            $inner = $this->newScope();
            foreach ($node->uses as $use) {
                if (is_string($use->var->name)) {
                    $inner->import($use->var->name, $scope);
                }
            }

            $this->walk($node->stmts, $this->declareParams($node->params, $inner));

            return;
        }

        if ($node instanceof ArrowFunction) {
            $this->walk($node->expr, $this->declareParams($node->params, $this->newScope($scope)));

            return;
        }

        if ($node instanceof Assign) {
            $this->recordFactory($node, $scope);
        }

        if ($node instanceof StaticCall && $this->isMockeryFactory($node) && $this->declaresReservedMethod($node)) {
            $node->setAttribute(self::KEEP_ATTRIBUTE, true);
        }

        if ($node instanceof CallLike && ! $node->isFirstClassCallable()) {
            $this->recordCallbacks($node);
        }

        if (($node instanceof MethodCall || $node instanceof StaticCall) && $this->isNames($node->name, self::REGISTRATIONS)) {
            $this->keepRegisteredFactories($node);
        }

        if ($node instanceof MethodCall && ! isset($this->seen[spl_object_id($node)])) {
            $this->recordChain($node, $scope);
        }

        foreach ($node->getSubNodeNames() as $name) {
            $subNode = $node->{$name};
            if ($subNode instanceof Node || is_array($subNode)) {
                $this->walk($subNode, $scope);
            }
        }
    }

    private function walkRepeating(Node $node, BindingScope $scope): void
    {
        $repeating = $this->repeating;
        $this->repeating = true;

        if ($node instanceof Closure || $node instanceof ArrowFunction) {
            $this->callbacks[spl_object_id($node)] = true;
            $this->walk($node, $scope);
        } else {
            foreach ($node->getSubNodeNames() as $name) {
                $subNode = $node->{$name};
                if ($subNode instanceof Node || is_array($subNode)) {
                    $this->walk($subNode, $scope);
                }
            }
        }

        $this->repeating = $repeating;
    }

    private function recordCallbacks(CallLike $call): void
    {
        if ($call instanceof FuncCall && $this->isNames($call->name, self::PEST_FUNCTIONS)) {
            $kind = match (true) {
                $this->isName($call->name, 'describe') => 'describe',
                $this->isNames($call->name, self::PEST_SETUP_FUNCTIONS) => 'setup',
                default => 'test',
            };

            foreach ($call->getArgs() as $arg) {
                if ($arg->value instanceof Closure || $arg->value instanceof ArrowFunction) {
                    $this->pestUnits[spl_object_id($arg->value)] = $kind;
                }
            }

            return;
        }

        foreach ($call->getArgs() as $arg) {
            if ($arg->value instanceof Closure || $arg->value instanceof ArrowFunction) {
                $this->callbacks[spl_object_id($arg->value)] = true;
            }
        }
    }

    private function recordFactory(Assign $assign, BindingScope $scope): void
    {
        $binding = $this->resolveBinding($assign->var, $scope);
        $factory = $assign->expr;

        while ($factory instanceof MethodCall && $this->isNames($factory->name, self::DOUBLE_MODES) && $factory->args === []) {
            $factory = $factory->var;
        }

        if ($binding !== null && $factory instanceof StaticCall && $this->isName($factory->class, 'Mockery') && ! $this->isMockeryFactory($factory)) {
            $this->matchers[$binding] = true;
        }

        if (! $factory instanceof StaticCall || ! $this->isMockeryFactory($factory)) {
            return;
        }

        if ($binding === null) {
            $factory->setAttribute(self::KEEP_ATTRIBUTE, true);

            return;
        }

        $this->factories[$binding][] = $factory;
    }

    private function keepRegisteredFactories(MethodCall|StaticCall $registration): void
    {
        foreach ($registration->args as $arg) {
            $factory = $arg instanceof Arg ? $arg->value : null;
            while ($factory instanceof MethodCall && $this->isNames($factory->name, self::DOUBLE_MODES) && $factory->args === []) {
                $factory = $factory->var;
            }

            if ($factory instanceof Expr && $this->isMockeryFactory($factory)) {
                $factory->setAttribute(self::KEEP_ATTRIBUTE, true);
            }
        }
    }

    private function recordChain(MethodCall $outermost, BindingScope $scope): void
    {
        $calls = [];
        $current = $outermost;
        while ($current instanceof MethodCall) {
            $this->seen[spl_object_id($current)] = true;
            array_unshift($calls, $current);
            $current = $current->var;
        }

        if ($this->isMockeryFactory($current)) {
            $modes = array_filter($calls, fn (MethodCall $call): bool => $this->isNames($call->name, self::DOUBLE_MODES) && $call->args === []);
            if (count($modes) !== count($calls)) {
                $current->setAttribute(self::KEEP_ATTRIBUTE, true);
            }

            return;
        }

        $binding = $this->resolveBinding($current, $scope);
        if ($binding === null) {
            return;
        }

        $root = $calls[0];
        if ($this->isNames($root->name, [...self::EXPECTATION_ROOTS, ...self::RECEIVED_ROOTS])) {
            $this->chains[$binding][] = $outermost;

            if ($this->repeating
                || ! $this->isSupportedChain($calls)
                || $this->repeatsExpectation($binding, $calls)
                || $this->usesMatcherVariable($calls, $scope)) {
                $this->disqualified[$binding] = true;
            }

            return;
        }

        if ($this->isName($root->name, 'shouldNotHaveBeenCalled')) {
            $this->chains[$binding][] = $outermost;

            if (count($calls) !== 1 || $root->args !== [] || $this->repeating) {
                $this->disqualified[$binding] = true;
            }

            return;
        }

        if ($this->isNames($root->name, self::MOCKERY_API) && ! $this->isPortableNativeExpectation($calls)) {
            $this->disqualified[$binding] = true;
        }
    }

    /** @param non-empty-list<MethodCall> $calls */
    private function isSupportedChain(array $calls): bool
    {
        $root = array_shift($calls);
        if (! $this->hasSupportedRootArguments($root)) {
            return false;
        }

        $allowed = $this->isNames($root->name, self::RECEIVED_ROOTS) ? self::RECEIVED_METHODS : self::EXPECTATION_METHODS;

        if ($this->isByDefault($calls) && (! $this->isName($root->name, 'shouldReceive')
            || array_filter($calls, fn (MethodCall $call): bool => $this->isNames($call->name, [...self::COUNT_METHODS, 'with', 'withArgs', 'withNoArgs'])) !== [])) {
            return false;
        }

        foreach ($calls as $index => $call) {
            if (! $this->isNames($call->name, $allowed) || ! $this->hasSupportedArguments($call)) {
                return false;
            }

            $next = $calls[$index + 1] ?? null;
            if ($this->isNames($call->name, ['atLeast', 'atMost']) && ! ($next instanceof MethodCall && $this->isName($next->name, 'times'))) {
                return false;
            }
        }

        return true;
    }

    /** @param non-empty-list<MethodCall> $calls */
    private function usesMatcherVariable(array $calls, BindingScope $scope): bool
    {
        foreach ($calls as $call) {
            foreach ($this->isName($call->name, 'with') ? $call->getArgs() : [] as $arg) {
                $binding = $this->resolveBinding($arg->value, $scope);
                if ($binding !== null && isset($this->matchers[$binding])) {
                    return true;
                }
            }
        }

        return false;
    }

    private function declaresReservedMethod(StaticCall $factory): bool
    {
        foreach ($this->targetClasses($factory) ?? [] as $class) {
            foreach (self::DOUBLE_RESERVED_METHODS as $reserved) {
                if ($class->hasNativeMethod($reserved) && ! $class->getNativeMethod($reserved)->isPrivate()) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return list<ClassReflection>|null */
    private function targetClasses(StaticCall $factory): ?array
    {
        $first = $factory->args[0] ?? null;
        $classes = [];

        foreach ($first instanceof Arg ? MockeryFactory::targets($first) ?? [] : [] as $target) {
            $name = $target->value instanceof String_
                ? ltrim($target->value->value, '\\')
                : ($target->value instanceof ClassConstFetch ? $this->getName($target->value->class) : null);

            if ($name === null || ! $this->reflectionProvider->hasClass($name)) {
                return null;
            }

            $classes[] = $this->reflectionProvider->getClass($name);
        }

        return $classes;
    }

    /** @param non-empty-list<MethodCall> $calls */
    private function repeatsExpectation(string $binding, array $calls): bool
    {
        $root = $calls[0];
        $method = $root->args[0] ?? null;
        if (! $this->isNames($root->name, self::EXPECTATION_ROOTS) || ! $method instanceof Arg || ! $method->value instanceof String_) {
            return false;
        }

        if ($this->isByDefault($calls)) {
            return false;
        }

        $with = array_values(array_filter($calls, fn (MethodCall $call): bool => $this->isName($call->name, 'with')));
        $arguments = $with === [] ? '*' : ($this->argumentSignature($with[0]) ?? '?');

        if (str_starts_with($binding, 'this->')) {
            $this->propertyExpectations[$binding][$this->unit][] = [$method->value->value, $arguments];

            return false;
        }

        $previous = $this->signatures[$binding][$method->value->value] ?? [];
        $this->signatures[$binding][$method->value->value][] = $arguments;

        return $this->conflicts($previous, $arguments);
    }

    /** @param list<string> $previous */
    private function conflicts(array $previous, string $arguments): bool
    {
        return in_array($arguments, $previous, true)
            || ($previous !== [] && ($arguments === '?' || in_array('?', $previous, true)))
            || in_array('*', $previous, true);
    }

    private function disqualifyRepeatedPropertyExpectations(): void
    {
        foreach ($this->propertyExpectations as $binding => $units) {
            $setup = [];
            foreach (array_intersect_key($units, $this->setupUnits) as $expectations) {
                $setup = [...$setup, ...$expectations];
            }

            $tests = array_diff_key($units, $this->setupUnits);
            foreach ($tests === [] ? [[]] : $tests as $expectations) {
                $previous = [];
                foreach ([...$setup, ...$expectations] as [$method, $arguments]) {
                    if ($this->conflicts($previous[$method] ?? [], $arguments)) {
                        $this->disqualified[$binding] = true;

                        continue 3;
                    }

                    $previous[$method][] = $arguments;
                }
            }
        }
    }

    /** @param list<MethodCall> $calls */
    private function isByDefault(array $calls): bool
    {
        return array_filter($calls, fn (MethodCall $call): bool => $this->isName($call->name, 'byDefault')) !== [];
    }

    private function enterUnit(bool $setup): string
    {
        $previous = $this->unit;
        $this->unit = 'unit'.++$this->unitCount;

        if ($setup) {
            $this->setupUnits[$this->unit] = true;
        }

        return $previous;
    }

    private function argumentSignature(MethodCall $with): ?string
    {
        $arguments = [];
        foreach ($with->args as $arg) {
            $value = $arg instanceof Arg ? $arg->value : null;
            $arguments[] = match (true) {
                $value instanceof String_ => 'string:'.$value->value,
                $value instanceof Int_ => 'int:'.$value->value,
                $value instanceof Float_ => 'float:'.$value->value,
                $value instanceof ConstFetch => 'const:'.$value->name->toString(),
                default => null,
            };
        }

        return in_array(null, $arguments, true) ? null : implode('|', $arguments);
    }

    private function hasSupportedRootArguments(MethodCall $root): bool
    {
        $first = $root->args[0] ?? null;
        if (! $first instanceof Arg) {
            return false;
        }

        if ($first->value instanceof String_ && str_contains($first->value->value, '->')) {
            return false;
        }

        return match (true) {
            $this->isName($root->name, 'shouldReceive') => count($root->args) === 1 && ! $first->value instanceof Array_,
            $this->isName($root->name, 'shouldNotReceive') => count($root->args) === 1 && $first->value instanceof String_,
            default => count($root->args) === 1
                || (count($root->args) === 2 && $root->args[1] instanceof Arg && $root->args[1]->value instanceof Array_),
        };
    }

    private function hasSupportedArguments(MethodCall $call): bool
    {
        if ($this->isNames($call->name, ['andReturnTrue', 'andReturnFalse', 'andReturnNull', 'andReturnSelf', 'withAnyArgs', 'zeroOrMoreTimes', 'byDefault'])) {
            return $call->args === [];
        }

        if ($this->isName($call->name, 'withArgs')) {
            return count($call->args) === 1;
        }

        if ($this->isNames($call->name, ['andThrow', 'andThrows'])) {
            $first = $call->args[0] ?? null;

            return $first instanceof Arg
                && (! $first->value instanceof ClassConstFetch || $first->value->class instanceof Name);
        }

        if ($this->isName($call->name, 'with')) {
            foreach ((new NodeFinder)->findInstanceOf($call->args, StaticCall::class) as $matcher) {
                if ($this->isName($matcher->class, 'Mockery')
                    && (! $this->isNames($matcher->name, self::MATCHERS) || ($this->isName($matcher->name, 'contains') && count($matcher->args) !== 1))) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @param non-empty-list<MethodCall> $calls */
    private function isPortableNativeExpectation(array $calls): bool
    {
        $root = $calls[0];
        $first = $root->args[0] ?? null;

        return count($calls) === 1
            && $this->isNames($root->name, ['allows', 'expects'])
            && count($root->args) === 1
            && $first instanceof Arg
            && $first->value instanceof String_;
    }

    private function markOwned(MethodCall $chain): void
    {
        $current = $chain;
        while ($current instanceof MethodCall) {
            $current->setAttribute(self::OWNED_ATTRIBUTE, true);

            $matchers = $this->isName($current->name, 'with') ? (new NodeFinder)->findInstanceOf($current->args, StaticCall::class) : [];
            foreach ($matchers as $matcher) {
                if ($this->isName($matcher->class, 'Mockery')) {
                    $matcher->setAttribute(self::OWNED_ATTRIBUTE, true);
                }
            }

            $current = $current->var;
        }
    }

    /**
     * @param  list<StaticCall>  $factories
     * @param  list<MethodCall>  $chains
     */
    private function fitsDouble(array $factories, array $chains): bool
    {
        $classes = [];
        foreach ($factories as $factory) {
            $targets = $this->targetClasses($factory);
            if ($targets === null) {
                return true;
            }

            if ($this->declaresReservedMethod($factory)) {
                return false;
            }

            $classes = [...$classes, ...$targets];
        }

        foreach ($chains as $chain) {
            $root = $chain;
            while ($root->var instanceof MethodCall) {
                $root = $root->var;
            }

            $method = $root->args[0] ?? null;
            if (! $method instanceof Arg || ! $method->value instanceof String_) {
                continue;
            }

            $declared = array_filter($classes, static fn (ClassReflection $class): bool => $class->hasNativeMethod($method->value->value));
            if ($declared === []) {
                return false;
            }
        }

        return true;
    }

    private function isMockeryFactory(Expr $expr): bool
    {
        return $expr instanceof StaticCall
            && $this->isName($expr->class, 'Mockery')
            && $this->isNames($expr->name, ['mock', 'spy']);
    }

    private function resolveBinding(Expr $expr, BindingScope $scope): ?string
    {
        if ($expr instanceof Variable && is_string($expr->name) && $expr->name !== 'this') {
            return $scope->resolve($expr->name);
        }

        if ($expr instanceof PropertyFetch && $expr->var instanceof Variable && $expr->name instanceof Identifier) {
            if ($expr->var->name === 'this') {
                return 'this->'.$expr->name->toString();
            }

            return is_string($expr->var->name) ? $scope->resolve($expr->var->name).'->'.$expr->name->toString() : null;
        }

        return null;
    }

    /** @param array<Node\Param> $params */
    private function declareParams(array $params, BindingScope $scope): BindingScope
    {
        foreach ($params as $param) {
            if ($param->var instanceof Variable && is_string($param->var->name)) {
                $scope->declare($param->var->name);
            }
        }

        return $scope;
    }

    private function newScope(?BindingScope $parent = null): BindingScope
    {
        return new BindingScope((string) ++$this->scopeCount, $parent);
    }
}
