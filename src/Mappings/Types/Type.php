<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Closure;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Mappings\Contracts\Type as TypeInterface;
use Sigmie\Query\Aggs;
use Sigmie\Shared\Contracts\Name;
use Sigmie\Shared\Contracts\ToRaw;

abstract class Type implements Name, ToRaw, TypeInterface
{
    protected string $type;

    protected array $meta = [];

    public function __construct(public string $name)
    {
    }

    public bool $hasQueriesCallback = false;

    public Closure $queriesClosure;

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

        if (!in_array($this->type(), ['nested', 'completion', 'object'])) {
            $raw[$this->name]['meta'] =
                [
                    ...$this->meta,
                    'class' => static::class,
                ];
        }

        return $raw;
    }

    abstract public function queries(string $queryString): array;

    public function aggregation(Aggs $aggs, string $params): void
    {
        return;
    }

    public function isFacetable(): bool
    {
        return false;
    }

    public function facets(ElasticsearchResponse $response): null|array
    {
        return null;
    }
}
