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
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
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
        'shouldAllowMockingProtectedMethods', 'shouldAllowMockingMethod', 'shouldHaveBeenCalled', 'shouldNotHaveBeenCalled',
    ];

    private const EXPECTATION_METHODS = [
        'with', 'withNoArgs', 'withAnyArgs', 'withArgs', 'andReturn', 'andReturnUsing', 'andReturnTrue', 'andReturnFalse',
        'andReturnNull', 'andReturnSelf', 'andThrow', 'andThrows', 'once', 'twice', 'times', 'atLeast', 'atMost', 'between',
        'never', 'ordered',
    ];

    private const RECEIVED_METHODS = ['with', 'withNoArgs', 'withAnyArgs', 'withArgs', 'once', 'twice', 'times', 'never'];

    private const MATCHERS = [
        'any', 'anyOf', 'type', 'on', 'capture', 'pattern', 'not', 'notAnyOf', 'contains', 'hasValue', 'hasKey', 'isSame',
        'mustBe', 'isEqual', 'andAnyOtherArgs', 'andAnyOthers', 'mock', 'spy',
    ];

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

        $this->walk($node->stmts, $this->newScope());

        foreach ($this->factories as $binding => $factories) {
            $owned = ! isset($this->disqualified[$binding])
                && array_filter($factories, static fn (StaticCall $factory): bool => ! MockeryFactory::isConvertible($factory)) === []
                && $this->declaresExpectedMethods($factories, $this->chains[$binding] ?? []);

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
            $this->walk($node->stmts ?? [], $this->declareParams($node->params, $this->newScope()));

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

    private function recordFactory(Assign $assign, BindingScope $scope): void
    {
        $binding = $this->resolveBinding($assign->var, $scope);
        $factory = $assign->expr;

        while ($factory instanceof MethodCall && $this->isNames($factory->name, self::DOUBLE_MODES) && $factory->args === []) {
            $factory = $factory->var;
        }

        if ($binding !== null && $this->isMockeryFactory($factory)) {
            $this->factories[$binding][] = $factory;
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

            if (! $this->isSupportedChain($calls)) {
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
        if ($root->getAttribute(RepeatedMockeryExpectationRector::SKIP_ATTRIBUTE) === true || ! $this->hasSupportedRootArguments($root)) {
            return false;
        }

        $allowed = $this->isNames($root->name, self::RECEIVED_ROOTS) ? self::RECEIVED_METHODS : self::EXPECTATION_METHODS;

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
        if ($this->isNames($call->name, ['andReturnTrue', 'andReturnFalse', 'andReturnNull', 'andReturnSelf', 'withAnyArgs'])) {
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
    private function declaresExpectedMethods(array $factories, array $chains): bool
    {
        $classes = [];
        foreach ($factories as $factory) {
            foreach (MockeryFactory::targets($factory->args[0]) ?? [] as $target) {
                $name = $target->value instanceof String_
                    ? ltrim($target->value->value, '\\')
                    : ($target->value instanceof ClassConstFetch ? $this->getName($target->value->class) : null);

                if ($name === null || ! $this->reflectionProvider->hasClass($name)) {
                    return true;
                }

                $classes[] = $this->reflectionProvider->getClass($name);
            }
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

    /** @phpstan-assert-if-true StaticCall $expr */
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

        if ($expr instanceof PropertyFetch
            && $expr->var instanceof Variable
            && $expr->var->name === 'this'
            && $expr->name instanceof Identifier) {
            return 'this->'.$expr->name->toString();
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
