<?php

declare(strict_types=1);

namespace MockeryToDouble\Rector\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use Rector\Rector\AbstractRector;

/** Migrates Mockery fluent chains that have a direct Double equivalent. */
final class MockeryExpectationRector extends AbstractRector
{
    private const KIND_ATTRIBUTE = 'mockery_to_double_chain_kind';

    private const EXPECTATION = 'expectation';

    private const RECEIVED = 'received';

    private const DOUBLE = 'double';

    /** @return array<class-string<Node>> */
    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    /** @param MethodCall $node */
    public function refactor(Node $node): ?Node
    {
        if (! $node->name instanceof Identifier) {
            return null;
        }

        $name = $node->name->toString();

        if ($name === 'shouldReceive'
            && $node->getAttribute(RepeatedMockeryExpectationRector::SKIP_ATTRIBUTE) !== true
            && count($node->args) === 1
            && $node->args[0] instanceof Arg
            && ! $node->args[0]->value instanceof Expr\Array_) {
            return $this->markAndRename($node, self::EXPECTATION, 'allows');
        }

        if ($name === 'shouldHaveReceived') {
            return $this->createReceivedCall($node, false);
        }

        if ($name === 'shouldNotHaveReceived') {
            return $this->createReceivedCall($node, true);
        }

        $kind = $this->findChainKind($node);

        if ($kind === self::DOUBLE) {
            return $this->refactorDoubleMode($node, $name);
        }

        if (! in_array($kind, [self::EXPECTATION, self::RECEIVED], true)) {
            return null;
        }

        if ($name === 'withNoArgs') {
            $node->args = [];

            return $this->rename($node, 'with');
        }

        if ($name === 'with' && $this->containsUnsupportedMockeryMatcher($node)) {
            return $this->restoreMockeryChain($node);
        }

        if ($name === 'withArgs' && count($node->args) === 1) {
            $matcher = new StaticCall(
                new FullyQualified('JMac\\Testing\\Matching\\Argument'),
                new Identifier('all'),
                $node->args,
            );
            $node->args = [new Arg($matcher)];

            return $this->rename($node, 'with');
        }

        if ($kind === self::RECEIVED) {
            return match ($name) {
                'once' => $this->replaceCountShortcut($node, 1),
                'twice' => $this->replaceCountShortcut($node, 2),
                'times', 'never', 'with' => null,
                default => $this->restoreMockeryChain($node),
            };
        }

        return match ($name) {
            'andReturn' => $this->rename($node, 'returns'),
            'andReturnUsing' => $this->rename($node, 'resolves'),
            'andThrow', 'andThrows' => $this->renameThrowableCall($node),
            'once' => $this->replaceExpectationCountShortcut($node, 1),
            'twice' => $this->replaceExpectationCountShortcut($node, 2),
            'times' => $this->refactorTimes($node),
            'between' => $this->refactorBetween($node),
            'with', 'never', 'ordered' => null,
            default => $this->restoreMockeryChain($node),
        };
    }

    private function refactorDoubleMode(MethodCall $node, string $name): ?Expr
    {
        if ($name === 'strict') {
            return null;
        }

        $factory = $this->findDoubleFactory($node);
        if (! $factory instanceof StaticCall) {
            return null;
        }

        if (in_array($name, ['makePartial', 'shouldDeferMissing'], true) && $node->args === []) {
            $node->var = $factory;

            return $this->markAndRename($node, self::DOUBLE, 'passthru');
        }

        if ($name === 'shouldIgnoreMissing' && $node->args === []) {
            return $factory;
        }

        $this->restoreMockeryDouble($node);

        return null;
    }

    private function createReceivedCall(MethodCall $node, bool $negated): ?MethodCall
    {
        if (count($node->args) < 1 || count($node->args) > 2) {
            return null;
        }

        $arguments = null;
        if (isset($node->args[1])) {
            $argumentList = $node->args[1];
            if (! $argumentList instanceof Arg || ! $argumentList->value instanceof Expr\Array_) {
                return null;
            }

            $arguments = [];
            foreach ($argumentList->value->items as $item) {
                $arguments[] = new Arg($item->value, unpack: $item->unpack);
            }

            $node->args = [$node->args[0]];
        }

        $received = $this->markAndRename($node, self::RECEIVED, 'received');
        $chain = $arguments === null
            ? $received
            : new MethodCall($received, new Identifier('with'), $arguments);
        $chain->setAttribute(self::KIND_ATTRIBUTE, self::RECEIVED);

        if (! $negated) {
            return $chain;
        }

        $never = new MethodCall($chain, new Identifier('never'));
        $never->setAttribute(self::KIND_ATTRIBUTE, self::RECEIVED);

        return $never;
    }

    private function renameThrowableCall(MethodCall $node): ?MethodCall
    {
        $firstArg = $node->args[0] ?? null;
        $throwable = $firstArg instanceof Arg ? $firstArg->value : null;

        if ($throwable === null) {
            return null;
        }

        if ($throwable instanceof String_) {
            $throwable = new New_(new FullyQualified($throwable->value), array_slice($node->args, 1));
            $node->args = [new Arg($throwable)];
        } elseif ($throwable instanceof ClassConstFetch && $this->isName($throwable->name, 'class')) {
            $class = $throwable->class;
            if (! $class instanceof Name) {
                return $this->restoreMockeryChain($node);
            }

            $throwable = new New_($class, array_slice($node->args, 1));
            $node->args = [new Arg($throwable)];
        }

        return $this->rename($node, 'throws');
    }

    private function replaceExpectationCountShortcut(MethodCall $node, int $count): MethodCall
    {
        $this->promoteExpectationRoot($node);

        if ($count === 1 && $node->var instanceof MethodCall) {
            return $node->var;
        }

        return $this->replaceCountShortcut($node, $count);
    }

    private function replaceCountShortcut(MethodCall $node, int $count): MethodCall
    {
        $node->name = new Identifier('times');
        $node->args = [new Arg(new LNumber($count))];

        return $node;
    }

    private function refactorTimes(MethodCall $node): MethodCall
    {
        if ($node->var instanceof MethodCall && $node->var->name instanceof Identifier) {
            $qualifier = $node->var->name->toString();

            if (in_array($qualifier, ['atLeast', 'atMost'], true)) {
                $node->var = $node->var->var;

                $countArg = $node->args[0] ?? null;
                if ($countArg instanceof Arg) {
                    $countArg->name = new Identifier($qualifier === 'atLeast' ? 'minimum' : 'maximum');
                }

                if ($qualifier === 'atLeast') {
                    $this->promoteExpectationRoot($node);
                }

                return $node;
            }
        }

        $countArg = $node->args[0] ?? null;
        $count = $countArg instanceof Arg ? $countArg->value : null;
        if ($count instanceof LNumber && $count->value > 0) {
            $this->promoteExpectationRoot($node);
        }

        return $node;
    }

    private function refactorBetween(MethodCall $node): MethodCall
    {
        $minimumArg = $node->args[0] ?? null;
        $minimum = $minimumArg instanceof Arg ? $minimumArg->value : null;
        if ($minimum instanceof LNumber && $minimum->value > 0) {
            $this->promoteExpectationRoot($node);
        }

        return $this->rename($node, 'times');
    }

    private function promoteExpectationRoot(MethodCall $node): void
    {
        $root = $this->findMarkedNode($node, self::EXPECTATION);

        if ($root instanceof MethodCall) {
            $root->name = new Identifier('expects');
        }
    }

    private function markAndRename(MethodCall $node, string $kind, string $name): MethodCall
    {
        $node->setAttribute(self::KIND_ATTRIBUTE, $kind);

        return $this->rename($node, $name);
    }

    private function rename(MethodCall $node, string $name): MethodCall
    {
        $node->name = new Identifier($name);

        return $node;
    }

    private function restoreMockeryChain(MethodCall $node): null
    {
        $current = $node->var;

        while ($current instanceof MethodCall) {
            if ($current->name instanceof Identifier) {
                $originalName = match ($current->name->toString()) {
                    'allows', 'expects' => 'shouldReceive',
                    'received' => 'shouldHaveReceived',
                    'returns' => 'andReturn',
                    'resolves' => 'andReturnUsing',
                    'throws' => 'andThrow',
                    default => null,
                };

                if ($originalName !== null) {
                    $current->name = new Identifier($originalName);
                }
            }

            $current->setAttribute(self::KIND_ATTRIBUTE, null);
            $current = $current->var;
        }

        return null;
    }

    private function containsUnsupportedMockeryMatcher(MethodCall $node): bool
    {
        $staticCalls = (new NodeFinder)->findInstanceOf($node->args, StaticCall::class);

        foreach ($staticCalls as $staticCall) {
            if ($this->isName($staticCall->class, 'Mockery')) {
                return true;
            }
        }

        return false;
    }

    private function restoreMockeryDouble(MethodCall $node): void
    {
        $factory = $this->findDoubleFactory($node);
        if (! $factory instanceof StaticCall) {
            return;
        }

        $factoryName = $factory->getAttribute('mockery_to_double_factory');
        $factory->class = new Name('Mockery');
        $factory->name = new Identifier(is_string($factoryName) ? $factoryName : 'mock');
        $factory->setAttribute(self::KIND_ATTRIBUTE, null);
        $node->var = $factory;
    }

    private function findDoubleFactory(MethodCall $node): ?StaticCall
    {
        $current = $node->var;

        while ($current instanceof MethodCall) {
            $current = $current->var;
        }

        return $current instanceof StaticCall
            && $current->getAttribute(self::KIND_ATTRIBUTE) === self::DOUBLE
            ? $current
            : null;
    }

    private function findChainKind(MethodCall $node): ?string
    {
        $current = $node->var;

        while ($current instanceof MethodCall || $current instanceof StaticCall) {
            $kind = $current->getAttribute(self::KIND_ATTRIBUTE);
            if (is_string($kind)) {
                return $kind;
            }

            $current = $current->var ?? null;
        }

        return null;
    }

    private function findMarkedNode(MethodCall $node, string $kind): MethodCall|StaticCall|null
    {
        $current = $node->var;

        while ($current instanceof MethodCall || $current instanceof StaticCall) {
            if ($current->getAttribute(self::KIND_ATTRIBUTE) === $kind) {
                return $current;
            }

            $current = $current->var ?? null;
        }

        return null;
    }
}
