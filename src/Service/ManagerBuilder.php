<?php

namespace Ni\Elastic\Service;

use Elasticsearch\Client as Elasticsearch;

class ManagerBuilder
{
    /**
     * Elasticsearch Client
     *
     * @var Elasticsearch
     */
    private $elasticsearch;

    public function __construct(Elasticsearch $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
    }

    public function build(): Manager
    {
        $manager = new Manager($this->elasticsearch);

        return $manager;
    }
}
