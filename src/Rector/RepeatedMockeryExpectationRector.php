<?php

declare(strict_types=1);

namespace MockeryToDouble\Rector\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
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
        $nodeFinder = new NodeFinder;
        $scopes = $this->findOutermostFunctionLikes($node->stmts);
        $scopedCalls = [];

        foreach ($scopes as $scope) {
            $methodCalls = array_values($nodeFinder->findInstanceOf($scope, MethodCall::class));
            $this->markRepeatedRoots($methodCalls);

            foreach ($methodCalls as $methodCall) {
                $scopedCalls[spl_object_id($methodCall)] = true;
            }
        }

        $this->markRepeatedRoots(array_values(array_filter(
            $nodeFinder->findInstanceOf($node->stmts, MethodCall::class),
            static fn (MethodCall $methodCall): bool => ! isset($scopedCalls[spl_object_id($methodCall)]),
        )));

        return null;
    }

    /**
     * @param  array<Node>  $nodes
     * @return list<FunctionLike>
     */
    private function findOutermostFunctionLikes(array $nodes): array
    {
        $nodeFinder = new NodeFinder;
        $functionLikes = $nodeFinder->findInstanceOf($nodes, FunctionLike::class);
        $nested = [];

        foreach ($functionLikes as $functionLike) {
            foreach ($nodeFinder->findInstanceOf((array) $functionLike->getStmts(), FunctionLike::class) as $inner) {
                $nested[spl_object_id($inner)] = true;
            }

            if ($functionLike instanceof ArrowFunction) {
                foreach ($nodeFinder->findInstanceOf($functionLike->expr, FunctionLike::class) as $inner) {
                    $nested[spl_object_id($inner)] = true;
                }
            }
        }

        return array_values(array_filter(
            $functionLikes,
            static fn (FunctionLike $functionLike): bool => ! isset($nested[spl_object_id($functionLike)]),
        ));
    }

    /** @param list<MethodCall> $methodCalls */
    private function markRepeatedRoots(array $methodCalls): void
    {
        /** @var array<string, list<MethodCall>> $rootsBySignature */
        $rootsBySignature = [];

        foreach ($methodCalls as $methodCall) {
            if (! $methodCall->name instanceof Identifier || ! in_array($methodCall->name->toString(), ['shouldReceive', 'shouldNotReceive'], true)) {
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
