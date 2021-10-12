<?php

declare(strict_types=1);

namespace Sigmie\Base\Search;

use Sigmie\Base\APIs\Search as APIsSearch;
use Sigmie\Base\Http\Responses\Search as SearchResponse;
use Sigmie\Base\Search\Queries\QueryClause;

class Search
{
    use APIsSearch;

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

    public function index(string $index): self
    {
        $this->index = $index;

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

    public function get(): SearchResponse
    {
        return $this->searchAPICall($this->index, $this->toRaw());
    }

    public function getDSL(): array
    {
        return $this->toRaw();
    }

    public function query(QueryClause $query)
    {
        $this->query = $query;

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
