<?php

declare(strict_types=1);

namespace MockeryToDouble\Rector\Support;

use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeFinder;
use Rector\PhpParser\Node\FileNode;

/** Lists the nodes of a file whose statement lists hold test code. */
final class StatementContainers
{
    /** @return list<FileNode|Namespace_|ClassMethod|Function_|Closure> */
    public static function in(FileNode $file): array
    {
        $nodeFinder = new NodeFinder;

        return array_values([
            $file,
            ...$nodeFinder->findInstanceOf($file->stmts, Namespace_::class),
            ...$nodeFinder->findInstanceOf($file->stmts, ClassMethod::class),
            ...$nodeFinder->findInstanceOf($file->stmts, Function_::class),
            ...$nodeFinder->findInstanceOf($file->stmts, Closure::class),
        ]);
    }
}
