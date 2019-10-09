<?php

namespace Ni\Elastic\Index;

use Elasticsearch\Client as Elasticsearch;
use Ni\Elastic\Collection;
use Ni\Elastic\Contract\Handler;
use Ni\Elastic\Element;
use Ni\Elastic\Contract\Manager;
use Ni\Elastic\Index\Actions\Create;
use Ni\Elastic\Index\Actions\Get;
use Ni\Elastic\Index\Actions\Remove;
use Ni\Elastic\Index\Actions\Listing;
use Ni\Elastic\Response\Factory;
use Ni\Elastic\Response\Response;
use Ni\Elastic\Index\Index;

class IndexManager implements Manager
{
    /**
     * Elasticsearch Client
     *
     * @var Elasticsearch
     */
    private $elasticsearch;

    private $handler;

    public function __construct(Elasticsearch $elasticsearch, Handler $handler)
    {
        $this->elasticsearch = $elasticsearch;
        $this->handler = $handler;
    }

    public function create(Element $index): Element
    {
        $params = [
            'index' => $index->getIdentifier()
        ];

        $response = $this->elasticsearch->indices()->create($params);

        return $this->handler->handle($response, new Create);
    }

    public function remove(string $identifier): bool
    {
        $params = [
            'index' => $identifier
        ];

        $response = $this->elasticsearch->indices()->delete($params);

        return $this->handler->handle($response, new Remove);
    }

    public function list(string $name = '*'): Collection
    {
        $params = [
            'index' => $name,
        ];

        $response = $this->elasticsearch->cat()->indices($params);

        return $this->handler->handle($response, new Listing);
    }

    public function get(string $name): Element
    {
        $params = [
            'index' => $name
        ];

        $response = $this->elasticsearch->indices()->get($params);

        return $this->handler->handle($response, new Get);
    }
}
