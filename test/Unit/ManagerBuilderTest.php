<?php

use Elasticsearch\Client as Elasticsearch;
use Ni\Elastic\Index\BaseIndex;
use Ni\Elastic\Service\Manager;
use Ni\Elastic\Service\ManagerBuilder;
use Ni\Elastic\Type\BaseType;
use PHPUnit\Framework\TestCase;

class ManagerBuilderTest extends TestCase
{
    /**
     * Elasticsearch mock
     *
     * @var Elasticsearch
     */
    private $esMock;

    public function setUp(): void
    {
        $this->esMock = $this->createMock(Elasticsearch::class);
    }
    /**
     * @test
     */
    public function buildMethod(): void
    {
        $builder = new ManagerBuilder($this->esMock);

        $this->assertInstanceOf(Manager::class, $builder->build());
    }

    /**
     * @test
     */
    public function managerDependecies(): void
    {
        $builder = new ManagerBuilder($this->esMock);

        $manager = $builder->build();
        $this->assertInstanceOf(BaseIndex::class,$manager->index());
        $this->assertInstanceOf(BaseType::class,$manager->type());
    }
}
