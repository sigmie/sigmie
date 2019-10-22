<?php

namespace Sigma\Test\Unit\Index;

use Elasticsearch\Client as Elasticsearch;
use PHPUnit\Framework\TestCase;
use Sigma\Contract\Subscribable;
use Sigma\Index\Action\Remove;

class RemoveTest extends TestCase
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
        $this->action = new Remove();

        $this->esMock = $this->createMock(Elasticsearch::class);

        $this->esMock->method('indices')->willReturn($this->esMock);
        $this->esMock->method('delete')->willReturn([]);
    }
    /**
     * @test
     */
    public function subscribable(): void
    {
        $this->assertInstanceOf(Subscribable::class, $this->action);
    }

    /**
     * @test
     */
    public function events(): void
    {
        $this->assertEquals($this->action->beforeEvent(), 'before.index.remove');
        $this->assertEquals($this->action->afterEvent(), 'after.index.remove');
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
        $this->esMock->expects($this->once())->method('delete')->with(['foo']);

        $this->action->execute($this->esMock, ['foo']);
    }
}
