<?php

declare(strict_types=1);

namespace MockeryToDouble\Rector\Support;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;

/** Decides which Mockery::mock() and Mockery::spy() calls have a direct Double::for() equivalent. */
final class MockeryFactory
{
    public static function isConvertible(StaticCall $factory): bool
    {
        $firstArg = $factory->args[0] ?? null;
        if (! $firstArg instanceof Arg) {
            return false;
        }

        if ($firstArg->value instanceof String_
            && (str_starts_with($firstArg->value->value, 'alias:') || str_starts_with($firstArg->value->value, 'overload:'))) {
            return false;
        }

        foreach (array_slice($factory->args, 1) as $arg) {
            if ($arg instanceof Arg && $arg->value instanceof Array_) {
                return false;
            }
        }

        return self::targets($firstArg) !== null;
    }

    /** @return list<Arg>|null */
    public static function targets(Node $arg): ?array
    {
        if (! $arg instanceof Arg) {
            return null;
        }

        if ($arg->value instanceof String_ && ! str_contains($arg->value->value, ',') && ! str_contains($arg->value->value, '[')) {
            return [$arg];
        }

        if (! $arg->value instanceof String_ && ! $arg->value instanceof Concat) {
            return [$arg];
        }

        $targets = [];
        $current = null;

        foreach (self::flattenConcat($arg->value) as $piece) {
            if ($piece instanceof ClassConstFetch && $piece->name instanceof Identifier && $piece->name->toLowerString() === 'class') {
                if ($current !== null) {
                    return null;
                }

                $current = $piece;

                continue;
            }

            if (! $piece instanceof String_ || str_contains($piece->value, '[')) {
                return null;
            }

            foreach (explode(',', $piece->value) as $index => $segment) {
                if ($index > 0) {
                    if ($current === null) {
                        return null;
                    }

                    $targets[] = new Arg($current);
                    $current = null;
                }

                $segment = trim($segment);
                if ($segment !== '') {
                    if ($current !== null) {
                        return null;
                    }

                    $current = new String_($segment);
                }
            }
        }

        if ($current === null) {
            return null;
        }

        $targets[] = new Arg($current);

        return $targets;
    }

    /** @return list<Expr> */
    private static function flattenConcat(Expr $expr): array
    {
        if (! $expr instanceof Concat) {
            return [$expr];
        }

        return [...self::flattenConcat($expr->left), ...self::flattenConcat($expr->right)];
    }
}
