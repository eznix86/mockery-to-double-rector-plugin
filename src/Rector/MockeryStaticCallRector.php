<?php

declare(strict_types=1);

namespace MockeryToDouble\Rector\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;

/** Migrates Mockery factories and argument matchers with direct equivalents. */
final class MockeryStaticCallRector extends AbstractRector
{
    private const KIND_ATTRIBUTE = 'mockery_to_double_chain_kind';

    /** @return array<class-string<Node>> */
    public function getNodeTypes(): array
    {
        return [StaticCall::class];
    }

    /** @param StaticCall $node */
    public function refactor(Node $node): ?Node
    {
        if (! $this->isName($node->class, 'Mockery') || ! $node->name instanceof Identifier) {
            return null;
        }

        $name = $node->name->toString();

        if (in_array($name, ['mock', 'spy'], true)) {
            if ($node->args === [] || $this->usesUnsupportedMockeryTarget($node) || $this->hasConstructorArguments($node)) {
                return null;
            }

            $node->class = new FullyQualified('JMac\\Testing\\Double');
            $node->name = new Identifier('for');
            $node->setAttribute(self::KIND_ATTRIBUTE, 'double');
            $node->setAttribute('mockery_to_double_factory', $name);

            if ($name === 'mock') {
                $strict = new MethodCall($node, new Identifier('strict'));
                $strict->setAttribute(self::KIND_ATTRIBUTE, 'double');
                $strict->setAttribute('mockery_to_double_factory', $name);

                return $strict;
            }

            return $node;
        }

        if ($name === 'notAnyOf') {
            $not = new StaticCall(
                new FullyQualified('JMac\\Testing\\Matching\\Argument'),
                new Identifier('not'),
            );

            return new MethodCall($not, new Identifier('any'), $node->args);
        }

        if (in_array($name, ['mustBe', 'isEqual'], true)) {
            $firstArg = $node->args[0] ?? null;

            return $firstArg instanceof Arg ? $firstArg->value : null;
        }

        if ($name === 'hasKey') {
            return $this->createHasKeyMatcher($node);
        }

        if ($name === 'contains' && count($node->args) !== 1) {
            return null;
        }

        $mappedName = match ($name) {
            'any', 'anyOf' => 'any',
            'type' => 'type',
            'on' => 'satisfies',
            'capture' => 'capture',
            'pattern' => 'matches',
            'not' => 'not',
            'contains', 'hasValue' => 'contains',
            'isSame' => 'same',
            'andAnyOtherArgs', 'andAnyOthers' => 'remaining',
            default => null,
        };

        if ($mappedName === null) {
            return null;
        }

        $node->class = new FullyQualified('JMac\\Testing\\Matching\\Argument');
        $node->name = new Identifier($mappedName);

        return $node;
    }

    private function createHasKeyMatcher(StaticCall $node): ?StaticCall
    {
        $firstArg = $node->args[0] ?? null;
        $expectedKey = $firstArg instanceof Arg ? $firstArg->value : null;
        if (! $expectedKey instanceof Node\Expr) {
            return null;
        }

        $predicate = new ArrowFunction([
            'static' => true,
            'params' => [
                new Param(new Variable('value')),
                new Param(new Variable('key')),
            ],
            'expr' => new Identical(new Variable('key'), $expectedKey),
        ]);

        return new StaticCall(
            new FullyQualified('JMac\\Testing\\Matching\\Argument'),
            new Identifier('contains'),
            [new Arg($predicate)],
        );
    }

    private function usesUnsupportedMockeryTarget(StaticCall $node): bool
    {
        $firstArg = $node->args[0] ?? null;
        if (! $firstArg instanceof Arg || ! $firstArg->value instanceof String_) {
            return false;
        }

        return str_starts_with($firstArg->value->value, 'alias:')
            || str_starts_with($firstArg->value->value, 'overload:');
    }

    private function hasConstructorArguments(StaticCall $node): bool
    {
        foreach (array_slice($node->args, 1) as $arg) {
            if ($arg instanceof Arg && $arg->value instanceof Array_) {
                return true;
            }
        }

        return false;
    }
}
