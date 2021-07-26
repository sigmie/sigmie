<?php

declare(strict_types=1);

namespace Sigmie\Base\Search;

use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\UriInterface;
use Sigmie\Base\Index\Index;

class Query
{
    private $queryClause;

    private string $indexName;

    private int $from = 0;

    private int $size = 1000;

    private array $only = ['*'];

    public function __construct($query)
    {
        $this->queryClause = $query;
    }

    public function index(Index $index)
    {
        $this->indexName = $index->name();

        return $this;
    }

    public function uri(): UriInterface
    {
        $uri = new Uri("/{$this->getIndexName()}/_search");

        return Uri::withQueryValue($uri, '_source', implode(',', $this->only));
    }

    public function toArray()
    {
        return [
            'query' => $this->queryClause,
            'from' => $this->from,
            'size' => $this->size,
        ];
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->toArray());
    }

    public function getIndexName(): string
    {
        return $this->indexName;
    }

    public function getTo()
    {
        return $this->size;
    }

    public function setSize($size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getFrom(): int
    {
        return $this->from;
    }

    public function setFrom($from): self
    {
        $this->from = $from;

        return $this;
    }
}
