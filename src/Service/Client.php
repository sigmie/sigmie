<?php

namespace Ni\Elastic\Service;

use Elasticsearch\ClientBuilder;
use Ni\Elastic\Builder;
use Elasticsearch\Client as Elasticsearch;

class Client
{
    /**
     * Hosts
     *
     * @var array
     */
    private $hosts;

    /**
     * Elastic search client
     *
     * @var Elasticsearch
     */
    private $elasticsearch;

    /**
     * Client builder
     *
     * @var ClientBuilder
     */
    private $builder;

    /**
     * Manager builder
     *
     * @var ManagerBuilder
     */
    private $managerBuilder;

    /**
     * Manager
     *
     * @var Manager
     */
    private $manager;

    /**
     * Class constructor
     *
     * @param array $hosts
     * @param Elasticsearch|null $elasticsearch
     * @param Builder|null $managerBuilder
     * @param ClientBuilder|null $builder
     */
    public function __construct(
        array $hosts = [],
        ?Elasticsearch $elasticsearch = null,
        ?Builder $managerBuilder = null,
        ?ClientBuilder $builder = null
    ) {
        $this->hosts = $hosts;

        if ($elasticsearch !== null) {
            $this->elasticsearch = $elasticsearch;
        }

        if ($managerBuilder !== null) {
            $this->managerBuilder = $managerBuilder;
        }

        if ($builder !== null) {
            $this->builder = $builder;
        }

        $this->elasticsearch();
    }

    /**
     * Get manager builder
     *
     * @return ManagerBuilder
     */
    public function getManagerBuilder(): ManagerBuilder
    {
        return $this->managerBuilder;
    }

    /**
     * Set manager builder
     *
     * @param  ManagerBuilder  $managerBuilder  Manager builder
     *
     * @return self
     */
    public function setManagerBuilder(ManagerBuilder $managerBuilder): self
    {
        $this->managerBuilder = $managerBuilder;

        return $this;
    }

    /**
     * Get elasticseach hosts
     *
     * @return array
     */
    public function getHosts(): array
    {
        return $this->hosts;
    }

    /**
     * Set elasticseach hosts
     *
     * @param array $host Elasticseach hosts
     *
     * @return self
     */
    public function setHosts(array $hosts): self
    {
        $this->hosts = $hosts;

        return $this;
    }

    /**
     * Get elastic search client
     *
     * @return Elasticsearch
     */
    public function getElasticsearch(): Elasticsearch
    {
        return $this->elasticsearch;
    }

    /**
     * Set elastic search client
     *
     * @param Elasticsearch  $elasticsearch  Elastic search client
     *
     * @return self
     */
    public function setElasticsearch(Elasticsearch $elasticsearch): self
    {
        $this->elasticsearch = $elasticsearch;

        return $this;
    }

    /**
     * Get client builder
     *
     * @return ClientBuilder
     */
    public function getBuilder(): ClientBuilder
    {
        return $this->builder;
    }

    /**
     * Set client builder
     *
     * @param  ClientBuilder  $builder  Client builder
     *
     * @return self
     */
    public function setBuilder(ClientBuilder $builder): self
    {
        $this->builder = $builder;

        return $this;
    }

    /**
     * Build the Elasticsearch client if the
     * elasticsearch is not initialized yet
     * and returns the instance
     *
     * @return Elasticsearch
     */
    public function elasticsearch(): Elasticsearch
    {
        if ($this->elasticsearch instanceof Elasticsearch) {
            return $this->elasticsearch;
        }

        $this->elasticsearch = $this->buildElasticsearch();

        return $this->elasticsearch;
    }

    /**
     * Build the Elasticsearch client
     *
     * @return Elasticsearch
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    private function buildElasticsearch(): Elasticsearch
    {
        if ($this->builder instanceof ClientBuilder) {
            return $this->builder
                ->setHosts($this->hosts)
                ->build();
        }

        $this->builder = ClientBuilder::create();

        return $this->buildElasticsearch();
    }

    /**
     * Build an return the manager instance
     *
     * @return Manager
     */
    public function manager(): Manager
    {
        if ($this->manager instanceof Manager) {
            return $this->manager;
        }

        if ($this->managerBuilder === null) {
            $this->managerBuilder = new ManagerBuilder($this->elasticsearch());
        }

        $this->manager = $this->managerBuilder->build();

        return $this->manager;
    }
}
