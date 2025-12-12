<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Closure;
use ReflectionClass;
use Sigmie\Mappings\Contracts\Type as TypeInterface;
use Sigmie\Search\Contracts\TextQueries;
use Sigmie\Shared\Contracts\Name;
use Sigmie\Shared\Contracts\ToRaw;

abstract class Type implements Name, TextQueries, ToRaw, TypeInterface
{
    protected string $type;

    protected array $meta = [];

    /**
     * Field path with optional nested boundary marker (>).
     *
     * Regular field:              "title"           → fullPath: "title", nestedPath: null
     * Inside Object_:             "meta.author"     → fullPath: "meta.author", nestedPath: null
     * Inside Nested:              "items>name"      → fullPath: "items.name", nestedPath: "items"
     * Inside Nested → Object_:    "items>details.price" → fullPath: "items.details.price", nestedPath: "items"
     * Nested inside Nested:       "orders>items>sku" → fullPath: "orders.items.sku", nestedPath: "orders"
     */
    protected string $path = '';

    public bool $hasQueriesCallback = false;

    public Closure $queriesClosure;

    public function __construct(public string $name) {}

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function fullPath(): string
    {
        $path = $this->path ?: $this->name;

        return str_replace('>', '.', $path);
    }

    public function nestedPath(): ?string
    {
        if (! str_contains($this->path, '>')) {
            return null;
        }

        return explode('>', $this->path)[0];
    }

    public function queries(array|string $queryString): array
    {
        return [];
    }

    public function queryStringQueries(array|string $queryString): array
    {
        if ($this->hasQueriesCallback) {
            return $this->queriesFromCallback($queryString);
        }

        return $this->queries($queryString);
    }

    public function queriesFromCallback(string $queryString): array
    {
        return ($this->queriesClosure)($queryString);
    }

    public function withQueries(Closure $closure): static
    {
        $this->hasQueriesCallback = true;
        $this->queriesClosure = $closure;

        return $this;
    }

    public function meta(array $meta): void
    {
        $this->meta = [...$this->meta, ...$meta];
    }

    public function isFacetable(): bool
    {
        return false;
    }

    public function __invoke(): array
    {
        return $this->toRaw();
    }

    public function type(): string
    {
        return $this->type;
    }

    public function name(): string
    {
        return $this->fullPath();
    }

    public function names(): array
    {
        return [
            $this->name,
        ];
    }

    public function toRaw(): array
    {
        $raw = [
            $this->name => [
                'type' => $this->type(),
            ],
        ];

        if (! in_array($this->type(), ['nested', 'completion', 'object'])) {
            $raw[$this->name]['meta'] =
                [
                    ...$this->meta,
                    'type' => $this->typeName(),
                ];
        }

        return $raw;
    }

    public function sortableName(): ?string
    {
        return $this->name;
    }

    public function validate(string $key, mixed $value): array
    {
        return [true, ''];
    }

    public function typeName(): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', (new ReflectionClass($this))->getShortName()));
    }
}
