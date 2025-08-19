<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Closure;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Enums\FacetLogic;
use Sigmie\Mappings\Contracts\Type as TypeInterface;
use Sigmie\Query\Aggs;
use Sigmie\Search\Contracts\TextQueries;
use Sigmie\Shared\Contracts\Name;
use Sigmie\Shared\Contracts\ToRaw;

abstract class Type implements Name, ToRaw, TypeInterface, TextQueries
{
    protected string $type;

    protected array $meta = [];

    protected FacetLogic $facetLogic = FacetLogic::Conjunctive;

    public function __construct(
        public string $name,
        public ?string $parentPath = null,
        public ?string $parentType = null,
        public ?string $fullPath = ''
    ) {}

    public bool $hasQueriesCallback = false;

    public Closure $queriesClosure;


    public function parent(
        string $parentPath,
        string $parentType,
    ): static {
        $this->parentPath = $parentPath;
        $this->parentType = $parentType;
        $this->fullPath = trim($parentPath . '.' . $this->name, '.');

        return $this;
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
        return trim("{$this->parentPath}.{$this->name}", '.');
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

    protected function typeName(): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', (new \ReflectionClass($this))->getShortName()));
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
}
