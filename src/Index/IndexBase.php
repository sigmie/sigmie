<?php

namespace Ni\Elastic\Index;

use Elasticsearch\Client as Elasticsearch;
use Elasticsearch\Endpoints\Cat\Repositories;
use JsonSerializable;
use Ni\Elastic\Exception\NotImplementedException;
use Ni\Elastic\Manageable;
use Ni\Elastic\Response\Response;
use Ni\Elastic\Response\Factory;

abstract class IndexBase implements Manageable
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
}
