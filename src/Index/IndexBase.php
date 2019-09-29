<?php

namespace Ni\Elastic\Index;

use Elasticsearch\Client as Elasticsearch;
use JsonSerializable;
use Ni\Elastic\Exception\NotImplementedException;

abstract class IndexBase
{
    /**
     * Elasticsearch Client
     *
     * @var Elasticsearch
     */
    protected $elasticsearch;

    public function __construct($elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
    }

    public function create($name)
    {
        $params = [
            'index' => $name
        ];

        //TODO return response object
        $response = $this->elasticsearch->indices()->create($params);

        return $response;
    }

    public function remove($name)
    {
        $params = [
            'index' => $name
        ];

        //TODO return response object
        $response = $this->elasticsearch->indices()->delete($params);

        return $response;
    }
}
