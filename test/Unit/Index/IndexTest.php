<?php

use Ni\Elastic\Exception\NotImplementedException;
use Ni\Elastic\Index\Index;
use PHPUnit\Framework\TestCase;
use Elasticsearch\Client as Elasticsearch;

class IndexTest extends TestCase
{
    /**
     * Elasticsearch mock
     *
     * @var Elasticsearch 
     */
    private $esMock;

    public function setup(): void
    {
        $this->esMock = $this->createMock(Elasticsearch::class);
        $this->esMock->method('indices')->willReturn($this->esMock);
    }

    /**
     * @test
     */
    public function createMethod(): void
    {
        $index = new Index($this->esMock);

        $this->esMock->expects($this->once())->method('create')->with(['index' => 'foo']);

        $index->create('foo');
    }

    /**
     * @test
     */
    public function removeMethod(): void
    {
        $index = new Index($this->esMock);

        $this->esMock->expects($this->once())->method('delete')->with(['index' => 'bar']);

        $index->remove('bar');
    }
}
