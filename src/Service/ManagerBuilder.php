<?php

namespace Ni\Elastic\Service;

use Elasticsearch\Client as Elasticsearch;
use Ni\Elastic\Index\BaseIndex;
use Ni\Elastic\Type\BaseType;

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
        $manager->setIndex(new BaseIndex());
        $manager->setType(new BaseType());

        return $manager;
    }
}
