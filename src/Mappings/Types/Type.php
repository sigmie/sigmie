<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Closure;
use ReflectionClass;
use Sigmie\Enums\FacetLogic;
use Sigmie\Mappings\Contracts\FieldContainer;
use Sigmie\Mappings\Contracts\FieldVisitor;
use Sigmie\Mappings\Contracts\Type as TypeInterface;
use Sigmie\Query\Aggs;
use Sigmie\Search\Contracts\TextQueries;
use Sigmie\Shared\Collection;
use Sigmie\Shared\Contracts\Name;
use Sigmie\Shared\Contracts\ToRaw;

abstract class Type implements Name, TextQueries, ToRaw, TypeInterface
{
    protected string $type;

    protected array $meta = [];

    protected FacetLogic $facetLogic = FacetLogic::Conjunctive;

    public function __construct(public string $name, protected ?Type $parent = null) {}

    public bool $hasQueriesCallback = false;

    public Closure $queriesClosure;

    public function setParent(?Type $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function parent(
        string|Type $parentPath,
        ?string $parentType = null,
    ): static {
        if ($parentPath instanceof Type) {
            $this->parent = $parentPath;

            return $this;
        }

        // Backward compatibility: accept string path, but we can't set parent reference
        // This will be deprecated once all code uses parent references
        return $this;
    }

    public function parentPath(): string
    {
        if (! $this->parent instanceof Type) {
            return '';
        }

        return $this->parent->fullPath();
    }

    public function fullPath(): string
    {
        $parentPath = $this->parentPath();

        if ($parentPath === '') {
            return $this->name;
        }

        return $parentPath.'.'.$this->name;
    }

    public function parentType(): ?string
    {
        return $this->parent instanceof Type ? $this->parent::class : null;
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

    public function meta(array $meta): void
    {
        $this->meta = [...$this->meta, ...$meta];
    }

    public function queriesFromCallback(string $queryString): array
    {
        return ($this->queriesClosure)($queryString);
    }

    public function withQueries(Closure $closure)
    {
        $this->hasQueriesCallback = true;
        $this->queriesClosure = $closure;

        return $this;
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
        return trim(sprintf('%s.%s', $this->parentPath(), $this->name), '.');
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
                    'class' => static::class,
                ];
        }

        return $raw;
    }

    public function aggregation(Aggs $aggs, string $params): void {}

    public function isFacetConjunctive(): bool
    {
        return $this->facetLogic === FacetLogic::Conjunctive;
    }

    public function isFacetDisjunctive(): bool
    {
        return $this->facetLogic === FacetLogic::Disjunctive;
    }

    public function isFacetable(): bool
    {
        return false;
    }

    public function facets(array $aggregation): ?array
    {
        return null;
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

    public function isFacetSearchable(): bool
    {
        return $this->facetLogic === FacetLogic::Searchable;
    }

    public function facetConjunctive(): static
    {
        $this->facetLogic = FacetLogic::Conjunctive;

        return $this;
    }

    public function facetDisjunctive(): static
    {
        $this->facetLogic = FacetLogic::Disjunctive;

        return $this;
    }

    public function vectorFields()
    {
        return (new Collection([]))
            ->map(fn (Nested|DenseVector $field): Nested|DenseVector => $field);
    }

    /**
     * Accept a visitor for tree traversal
     */
    public function accept(FieldVisitor $visitor): mixed
    {
        return $visitor->visit($this);
    }

    /**
     * Walk through this field and all nested fields, calling the callback for each
     */
    public function walk(callable $callback): void
    {
        $callback($this);

        if ($this instanceof FieldContainer && $this->hasFields()) {
            $properties = $this->getProperties();
            foreach ($properties->fields() as $field) {
                $field->walk($callback);
            }
        }
    }
}
