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

    public function toJson($options = 0)
    {
        return json_encode($this->toArray());
    }

    /**
     * Get the value of indexName
     */
    public function getIndexName()
    {
        return $this->indexName;
    }

    /**
     * Get the value of to
     */
    public function getTo()
    {
        return $this->size;
    }

    /**
     * Set the value of to
     *
     * @return  self
     */
    public function setSize($size)
    {
        $this->size = $size;

        return $this;
    }

    /**
     * Get the value of from
     */
    public function getFrom()
    {
        return $this->from;
    }

    /**
     * Set the value of from
     *
     * @return  self
     */
    public function setFrom($from)
    {
        $this->from = $from;

        return $this;
    }
}
