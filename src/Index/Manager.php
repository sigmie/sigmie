<?php

namespace Ni\Elastic\Index;

use Ni\Elastic\Manageable;
use Elasticsearch\Client as Elasticsearch;
use Ni\Elastic\Response\Factory;
use Ni\Elastic\Response\Response;

class Manager implements Manageable
{
    /**
     * Elasticsearch Client
     *
     * @var Elasticsearch
     */
    private $elasticsearch;

    /**
     * Response factory
     *
     * @var Factory
     */
    private $responseFactory;

    public function __construct(Elasticsearch $elasticsearch, Factory $responseFactory)
    {
        $this->elasticsearch = $elasticsearch;
        $this->responseFactory = $responseFactory;
    }

    public function create(array $values): Response
    {
        $params = [
            'index' => $values['name']
        ];

        $response = $this->elasticsearch->indices()->create($params);

        return $this->responseFactory->create($response);
    }

    public function remove(string $identifier): Response
    {
        $params = [
            'index' => $identifier
        ];

        $response = $this->elasticsearch->indices()->delete($params);

        return $this->responseFactory->create($response);
    }

    public function list(array $params = ['index' => '*']): Response
    {
        $response = $this->elasticsearch->cat()->indices($params);

        return $this->responseFactory->create($response);
    }

    public function get(array $params = ['index' => '*']): Response
    {
        $response = $this->elasticsearch->indices()->get($params);

        return $this->responseFactory->create($response);
    }
}
