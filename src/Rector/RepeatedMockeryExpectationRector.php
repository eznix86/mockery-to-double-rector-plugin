<?php

declare(strict_types=1);

namespace MockeryToDouble\Rector\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar;
use PhpParser\NodeFinder;
use Rector\PhpParser\Node\FileNode;
use Rector\Rector\AbstractRector;

/** Preserves repeated Mockery expectations whose direct conversion reverses return order. */
final class RepeatedMockeryExpectationRector extends AbstractRector
{
    public const SKIP_ATTRIBUTE = 'mockery_to_double_repeated_expectation';

    /** @return array<class-string<Node>> */
    public function getNodeTypes(): array
    {
        return [FileNode::class];
    }

    /** @param FileNode $node */
    public function refactor(Node $node): null
    {
        $methodCalls = array_values((new NodeFinder)->findInstanceOf($node->stmts, MethodCall::class));

        /** @var array<string, list<MethodCall>> $rootsBySignature */
        $rootsBySignature = [];

        foreach ($methodCalls as $methodCall) {
            if (! $methodCall->name instanceof Identifier || $methodCall->name->toString() !== 'shouldReceive') {
                continue;
            }

            $signature = $this->resolveSignature($methodCall, $methodCalls);
            if ($signature !== null) {
                $rootsBySignature[$signature][] = $methodCall;
            }
        }

        foreach ($rootsBySignature as $roots) {
            if (count($roots) < 2) {
                continue;
            }

            foreach ($roots as $root) {
                $root->setAttribute(self::SKIP_ATTRIBUTE, true);
            }
        }

        return null;
    }

    /** @param list<MethodCall> $methodCalls */
    private function resolveSignature(MethodCall $root, array $methodCalls): ?string
    {
        if (! $root->var instanceof Variable || ! is_string($root->var->name)) {
            return null;
        }

        $methodArg = $root->args[0] ?? null;
        if (! $methodArg instanceof Arg || ! $methodArg->value instanceof Scalar\String_) {
            return null;
        }

        $outermost = $root;
        $maximumDepth = 0;

        foreach ($methodCalls as $candidate) {
            $depth = $this->distanceFromRoot($candidate, $root);
            if ($depth !== null && $depth > $maximumDepth) {
                $outermost = $candidate;
                $maximumDepth = $depth;
            }
        }

        $withSignature = $this->resolveWithSignature($outermost, $root);
        if ($withSignature === null) {
            return null;
        }

        return $root->var->name.'::'.$methodArg->value->value.'::'.$withSignature;
    }

    private function distanceFromRoot(MethodCall $candidate, MethodCall $root): ?int
    {
        $current = $candidate;
        $depth = 0;

        while ($current instanceof MethodCall) {
            if ($current === $root) {
                return $depth;
            }

            $current = $current->var;
            $depth++;
        }

        return null;
    }

    private function resolveWithSignature(MethodCall $outermost, MethodCall $root): ?string
    {
        $current = $outermost;

        while ($current !== $root) {
            if ($current->name instanceof Identifier && $current->name->toString() === 'with') {
                $arguments = [];
                foreach ($current->args as $arg) {
                    if (! $arg instanceof Arg) {
                        return null;
                    }

                    $arguments[] = $this->resolveValueSignature($arg->value);
                }

                if (in_array(null, $arguments, true)) {
                    return null;
                }

                return implode('|', $arguments);
            }

            if (! $current->var instanceof MethodCall) {
                break;
            }

            $current = $current->var;
        }

        return '*';
    }

    private function resolveValueSignature(Expr $expr): ?string
    {
        return match (true) {
            $expr instanceof Scalar\String_ => 'string:'.$expr->value,
            $expr instanceof Scalar\LNumber => 'int:'.$expr->value,
            $expr instanceof Scalar\DNumber => 'float:'.$expr->value,
            $expr instanceof Expr\ConstFetch => 'const:'.$expr->name->toString(),
            default => null,
        };
    }
}
