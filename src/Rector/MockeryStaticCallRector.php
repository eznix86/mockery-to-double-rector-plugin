<?php

declare(strict_types=1);

namespace MockeryToDouble\Rector\Rector;

use MockeryToDouble\Rector\Support\MockeryFactory;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
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
            $targets = MockeryFactory::isConvertible($node) && $node->getAttribute(MockeryDoubleOwnershipRector::KEEP_ATTRIBUTE) !== true
                ? MockeryFactory::targets($node->args[0])
                : null;
            if ($targets === null) {
                return null;
            }

            $node->args = [...$targets, ...array_slice($node->args, 1)];
            $node->class = new FullyQualified('JMac\\Testing\\Double');
            $node->name = new Identifier('for');
            $node->setAttribute(self::KIND_ATTRIBUTE, 'double');
            $node->setAttribute('mockery_to_double_factory', $name);

            return $node;
        }

        if ($node->getAttribute(MockeryDoubleOwnershipRector::OWNED_ATTRIBUTE) !== true) {
            return null;
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
        if (! $expectedKey instanceof Expr) {
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
}
