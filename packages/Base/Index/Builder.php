<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Carbon\Carbon;
use Sigmie\Base\APIs\Calls\Index;
use Sigmie\Base\Contracts\HttpConnection;

class Builder
{
    use Index;

    private int $replicas = 2;

    private int $shards = 1;

    private string $prefix = '';

    private bool $dynamicMappings = false;

    public function __construct(HttpConnection $connection)
    {
        $this->setHttpConnection($connection);
    }

    public function alias(string $alias)
    {
        return $this;
    }

    public function language()
    {
        return $this;
    }

    public function prefix(string $prefix)
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function withLanguageDefaults()
    {
        return $this;
    }

    public function withDefaultStopwords()
    {
        return $this;
    }

    public function withoutMappings()
    {
        $this->dynamicMappings = true;

        return $this;
    }

    public function tokenizeOn()
    {
        return $this;
    }

    public function mappings()
    {
        return $this;
    }

    public function stopwords()
    {
        return $this;
    }

    public function synonyms()
    {
        return $this;
    }

    public function stemming()
    {
        return $this;
    }

    public function keywords()
    {
        return $this;
    }

    public function shards(int $shards)
    {
        $this->shards = $shards;

        return $this;
    }

    public function replicas(int $replicas)
    {
        $this->replicas = $replicas;

        return $this;
    }

    public function create()
    {
        $name = Carbon::now()->format('YmdHisu');

        $this->indexAPICall("/{$name}", 'PUT');

        return;
    }
}
