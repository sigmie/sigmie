<?php

namespace Sigma\Test\Unit\Index;

use Elasticsearch\Client as Elasticsearch;
use PHPUnit\Framework\TestCase;
use Sigma\Contract\Subscribable;
use Sigma\Index\Action\Listing;

class ListingTest extends TestCase
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
        $this->action = new Listing();

        $this->esMock = $this->createMock(Elasticsearch::class);

        $this->esMock->method('cat')->willReturn($this->esMock);
        $this->esMock->method('indices')->willReturn([]);
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
        $this->esMock->expects($this->once())->method('cat');
        $this->esMock->expects($this->once())->method('indices')->with(['foo']);

        $this->action->execute($this->esMock, ['foo']);
    }
}
