<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Closure;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Mappings\Contracts\Type as TypeInterface;
use Sigmie\Query\Aggs;
use Sigmie\Search\Contracts\TextQueries;
use Sigmie\Shared\Contracts\Name;
use Sigmie\Shared\Contracts\ToRaw;

abstract class Type implements Name, ToRaw, TypeInterface, TextQueries
{
    protected string $type;

    protected array $meta = [];

    public function __construct(
        public string $name,
        public ?string $parentPath = null,
        public ?string $parentType = null,
        public ?string $fullPath = null,
    ) {}

    public bool $hasQueriesCallback = false;

    public Closure $queriesClosure;

    public function parent(string $parentPath, string $parentType, ?string $parentFullPath): static
    {
        $this->parentPath = $parentPath;
        $this->parentType = $parentType;

        if ($parentFullPath) {
            $this->fullPath = $parentFullPath . '.' . $this->name;
        }

        return $this;
    }

    public function queries(array|string $queryString): array
    {
        return [];
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
        if (! is_null($this->parentPath)) {
            return "{$this->parentPath}.{$this->name}";
        }

        return $this->name;
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

    public function isFacetable(): bool
    {
        return false;
    }

    public function facets(ElasticsearchResponse $response): ?array
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
}
