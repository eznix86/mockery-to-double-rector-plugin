<?php

declare(strict_types=1);

namespace MockeryToDouble\Rector\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;
use Rector\Rector\AbstractRector;

/** Merges consecutive single-call Mockery expectations into one sequential answer. */
final class SequentialMockeryExpectationRector extends AbstractRector
{
    /** @return array<class-string<Node>> */
    public function getNodeTypes(): array
    {
        return [ClassMethod::class, Function_::class, Closure::class];
    }

    /** @param ClassMethod|Function_|Closure $node */
    public function refactor(Node $node): ?Node
    {
        if ($node->stmts === null) {
            return null;
        }

        $stmts = [];
        $changed = false;
        $run = [];

        foreach ($node->stmts as $stmt) {
            $expectation = $this->parseExpectation($stmt);

            if ($expectation !== null && $run !== [] && $this->isSameExpectation($run[0][1], $expectation)) {
                $run[] = [$stmt, $expectation];

                continue;
            }

            $changed = $this->flushRun($run, $stmts, $node->stmts) || $changed;
            $run = $expectation === null ? [] : [[$stmt, $expectation]];

            if ($expectation === null) {
                $stmts[] = $stmt;
            }
        }

        $changed = $this->flushRun($run, $stmts, $node->stmts) || $changed;

        if (! $changed) {
            return null;
        }

        $node->stmts = $stmts;

        return $node;
    }

    /**
     * @param  list<array{Stmt, array{root: MethodCall, with: ?MethodCall, answer: Expr}}>  $run
     * @param  list<Stmt>  $stmts
     * @param  array<Stmt>  $scope
     */
    private function flushRun(array $run, array &$stmts, array $scope): bool
    {
        if (count($run) < 2 || $this->countRoots($scope, $run[0][1]['root']) !== count($run)) {
            foreach ($run as [$stmt]) {
                $stmts[] = $stmt;
            }

            return false;
        }

        $first = $run[0][1];
        $chain = new MethodCall($first['root']->var, new Identifier('shouldReceive'), $first['root']->args);
        if ($first['with'] instanceof MethodCall) {
            $chain = new MethodCall($chain, new Identifier('with'), $first['with']->args);
        }

        $chain = new MethodCall($chain, new Identifier('times'), [new Arg(new LNumber(count($run)))]);
        $chain = new MethodCall($chain, new Identifier('andReturn'), array_map(
            static fn (array $item): Arg => new Arg($item[1]['answer']),
            $run,
        ));

        $merged = new Expression($chain);
        $comments = [];
        foreach ($run as [$stmt]) {
            $comments = [...$comments, ...$stmt->getComments()];
        }

        $merged->setAttribute('comments', $comments);
        $stmts[] = $merged;

        return true;
    }

    /** @return array{root: MethodCall, with: ?MethodCall, answer: Expr}|null */
    private function parseExpectation(Stmt $stmt): ?array
    {
        if (! $stmt instanceof Expression || ! $stmt->expr instanceof MethodCall) {
            return null;
        }

        $calls = [];
        $current = $stmt->expr;
        while ($current instanceof MethodCall) {
            if (! $current->name instanceof Identifier) {
                return null;
            }

            $calls[$current->name->toString()][] = $current;
            $current = $current->var;
        }

        $root = $calls['shouldReceive'][0] ?? null;
        $answer = $calls['andReturn'][0] ?? null;
        $with = $calls['with'][0] ?? null;

        if (! $root instanceof MethodCall
            || ! $answer instanceof MethodCall
            || ! isset($calls['once'])
            || array_diff(array_keys($calls), ['shouldReceive', 'with', 'once', 'andReturn']) !== []
            || array_filter($calls, static fn (array $group): bool => count($group) > 1) !== []
            || $root->var !== $current
            || ! $current instanceof Variable
            || count($root->args) !== 1
            || ! $root->args[0] instanceof Arg
            || ! $root->args[0]->value instanceof String_
            || count($answer->args) !== 1
            || ! $answer->args[0] instanceof Arg
            || $answer->args[0]->unpack) {
            return null;
        }

        return ['root' => $root, 'with' => $with, 'answer' => $answer->args[0]->value];
    }

    /**
     * @param  array{root: MethodCall, with: ?MethodCall, answer: Expr}  $left
     * @param  array{root: MethodCall, with: ?MethodCall, answer: Expr}  $right
     */
    private function isSameExpectation(array $left, array $right): bool
    {
        return $this->nodeComparator->areNodesEqual($left['root'], $right['root'])
            && $this->nodeComparator->areNodesEqual($left['with']->args ?? [], $right['with']->args ?? []);
    }

    /** @param array<Stmt> $scope */
    private function countRoots(array $scope, MethodCall $root): int
    {
        $roots = (new NodeFinder)->find($scope, fn (Node $node): bool => $node instanceof MethodCall
            && $this->isNames($node->name, ['shouldReceive', 'shouldNotReceive'])
            && $this->nodeComparator->areNodesEqual($node->var, $root->var)
            && $this->nodeComparator->areNodesEqual($node->args, $root->args));

        return count($roots);
    }
}
