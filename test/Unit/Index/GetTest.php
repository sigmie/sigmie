<?php

use Elasticsearch\Client as Elasticsearch;
use PHPUnit\Framework\TestCase;
use Sigma\Contract\Subscribable;
use Sigma\Index\Action\Get;

class InsertTest extends TestCase
{
    /**
     * @var Insert
     */
    private $action;

    /**
     * @var Elasticsearch
     */
    private $esMock;

    public function setUp(): void
    {
        $this->action = new Get();

        $this->esMock = $this->createMock(Elasticsearch::class);

        $this->esMock->method('indices')->willReturn($this->esMock);
        $this->esMock->method('get')->willReturn([]);
    }
    /**
     * @test
     */
    public function subscribable(): void
    {
        $this->assertNotInstanceOf(Subscribable::class, $this->action);
    }

    /**
     * @test
     */
    public function prepare(): void
    {
        $prepared = $this->action->prepare('foo');

        $this->assertEquals(['index' => 'foo'], $prepared);
    }

    /**
     * @test
     */
    public function execute(): void
    {
        $this->esMock->expects($this->once())->method('indices');
        $this->esMock->expects($this->once())->method('get')->with(['foo']);

        $this->action->execute($this->esMock, ['foo']);
    }
}
