<?php

declare(strict_types=1);

namespace MockeryToDouble\Rector\Support;

/** Resolves a variable name to the binding it refers to, following closure `use` and arrow function capture. */
final class BindingScope
{
    /** @var array<string, string> */
    private array $bindings = [];

    public function __construct(
        private readonly string $id,
        private readonly ?self $parent = null,
    ) {}

    public function resolve(string $name): string
    {
        if (isset($this->bindings[$name])) {
            return $this->bindings[$name];
        }

        return $this->bindings[$name] = $this->parent instanceof self
            ? $this->parent->resolve($name)
            : $this->id.'$'.$name;
    }

    public function declare(string $name): void
    {
        $this->bindings[$name] = $this->id.'$'.$name;
    }

    public function import(string $name, self $from): void
    {
        $this->bindings[$name] = $from->resolve($name);
    }
}
