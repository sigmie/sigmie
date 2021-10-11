<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Builders;

use Sigmie\Base\Search\Queries\QueryClause;

abstract class Search
{
    protected string $index;

    protected int $from = 0;

    protected int $size = 500;

    protected array $fields = ['*'];

    protected QueryClause $query;

    protected array $sort = [];

    public function fields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    public function from(int $from): self
    {
        $this->from = $from;

        return $this;
    }

    public function size(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function sortAsc(string $field): self
    {
        $this->sort[] = [$field => 'asc'];

        return $this;
    }

    public function sortDesc(string $field): self
    {
        $this->sort[] = [$field => 'desc'];

        return $this;
    }

    public function toRaw(): array
    {
        return [
            '_source' => $this->fields,
            'query' => $this->query->toRaw(),
            'from' => $this->from,
            'size' => $this->size,
            'sort' => [...$this->sort, '_score']
        ];
    }
}
