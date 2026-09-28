<?php

declare(strict_types=1);

namespace MockeryToDouble\Rector\Rector;

use MockeryToDouble\Rector\Support\StatementContainers;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeFinder;
use Rector\PhpParser\Node\FileNode;
use Rector\Rector\AbstractRector;

/** Expands the `Mockery::mock(Foo::class, ['method' => $value])` shorthand into one expectation per method. */
final class MockeryQuickDefinitionRector extends AbstractRector
{
    /** @return array<class-string<Node>> */
    public function getNodeTypes(): array
    {
        return [FileNode::class];
    }

    /** @param FileNode $node */
    public function refactor(Node $node): ?Node
    {
        $changed = false;

        foreach (StatementContainers::in($node) as $container) {
            if ($container->stmts === null) {
                continue;
            }

            $stmts = [];
            foreach ($container->stmts as $stmt) {
                $stmts[] = $stmt;
                $expectations = $this->expand($stmt, $node);

                if ($expectations !== []) {
                    $stmts = [...$stmts, ...$expectations];
                    $changed = true;
                }
            }

            $container->stmts = $stmts;
        }

        return $changed ? $node : null;
    }

    /** @return list<Expression> */
    private function expand(Stmt $stmt, FileNode $file): array
    {
        if (! $stmt instanceof Expression || ! $stmt->expr instanceof Assign) {
            return [];
        }

        $receiver = $stmt->expr->var;
        $factory = $stmt->expr->expr;

        if (! $this->isReceiver($receiver)
            || ! $factory instanceof StaticCall
            || ! $this->isName($factory->class, 'Mockery')
            || ! $this->isNames($factory->name, ['mock', 'spy'])
            || count($factory->args) !== 2
            || ! $factory->args[1] instanceof Arg
            || ! $factory->args[1]->value instanceof Array_) {
            return [];
        }

        $definitions = $this->definitions($factory->args[1]->value);
        if ($definitions === null || $this->hasOtherExpectation($file, $receiver, array_keys($definitions))) {
            return [];
        }

        $factory->args = [$factory->args[0]];

        $expectations = [];
        foreach ($definitions as $method => $value) {
            $expectation = new MethodCall(clone $receiver, new Identifier('shouldReceive'), [new Arg(new String_($method))]);
            $expectations[] = new Expression(new MethodCall($expectation, new Identifier('andReturn'), [new Arg($value)]));
        }

        return $expectations;
    }

    /** @return array<string, Expr>|null */
    private function definitions(Array_ $array): ?array
    {
        $definitions = [];

        foreach ($array->items as $item) {
            if ($item->byRef || $item->unpack || ! $item->key instanceof String_) {
                return null;
            }

            $definitions[$item->key->value] = $item->value;
        }

        return $definitions === [] ? null : $definitions;
    }

    /** @param list<string> $methods */
    private function hasOtherExpectation(FileNode $file, Expr $receiver, array $methods): bool
    {
        $expectations = (new NodeFinder)->find($file->stmts, fn (Node $node): bool => $node instanceof MethodCall
            && $this->isNames($node->name, ['shouldReceive', 'shouldNotReceive', 'allows', 'expects'])
            && $this->nodeComparator->areNodesEqual($node->var, $receiver)
            && ($node->args[0] ?? null) instanceof Arg
            && (! $node->args[0]->value instanceof String_ || in_array($node->args[0]->value->value, $methods, true)));

        return $expectations !== [];
    }

    private function isReceiver(Expr $expr): bool
    {
        if ($expr instanceof Variable) {
            return is_string($expr->name) && $expr->name !== 'this';
        }

        return $expr instanceof PropertyFetch
            && $expr->var instanceof Variable
            && $expr->var->name === 'this'
            && $expr->name instanceof Identifier;
    }
}
