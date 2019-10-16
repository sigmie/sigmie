<?php
namespace Sigma\Test\Unit;

use Elasticsearch\Client as Elasticsearch;
use PHPUnit\Framework\TestCase;
use Sigma\ActionDispatcher;
use Sigma\Contract\Action;
use Sigma\Contract\Subscribable;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as EventDispatcher;

class ActionDispatcherTest extends TestCase
{
    private $elastisearchMock;

    private $eventDispatcherMock;

    private $actionMock;

    private $actionDispatcher;

    public function setUp(): void
    {
        $this->elastisearchMock = $this->createMock(Elasticsearch::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $this->actionMock = $this->createMock(Action::class);

        $this->actionDispatcher = new ActionDispatcher($this->elastisearchMock, $this->eventDispatcherMock);
    }
    /**
     * @test
     */
    public function execute(): void
    {
        $this->actionMock->method('prepare')->willReturn(['bar']);

        $this->actionMock->expects($this->once())->method('execute')->with($this->elastisearchMock, ['bar']);

        $this->actionDispatcher->dispatch(['foo'], $this->actionMock);
    }

    /**
     * @test
     */
    public function prepare(): void
    {
        $this->actionMock->expects($this->once())->method('prepare')->with(['foo']);

        $this->actionDispatcher->dispatch(['foo'], $this->actionMock);
    }

    /**
     * @test
     */
    public function eventListeners(): void
    {
        $actionMock = $actionMock = $this->createMock([Action::class, Subscribable::class]);

        $actionMock->method('beforeEvent')->willReturn('before.foo.bar');
        $actionMock->method('afterEvent')->willReturn('after.foo.bar');

        $this->eventDispatcherMock->expects($this->at(0))->method('hasListeners')->with('before.foo.bar');
        $this->eventDispatcherMock->expects($this->at(1))->method('hasListeners')->with('after.foo.bar');

        $this->actionDispatcher->dispatch([], $actionMock);
    }
}
