<?php

namespace Sigma\Test\Unit;

use Elasticsearch\Client as Elasticsearch;
use PHPUnit\Framework\TestCase;
use Sigma\ActionDispatcher;
use Sigma\Contract\Action;
use Sigma\Contract\Subscribable;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as EventDispatcher;
use Symfony\Component\EventDispatcher\GenericEvent;

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

        $this->actionDispatcher->dispatch($this->actionMock, ['foo']);
    }

    /**
     * @test
     */
    public function prepare(): void
    {
        $this->actionMock->expects($this->once())->method('prepare')->with(['foo']);

        $this->actionDispatcher->dispatch($this->actionMock, ['foo']);
    }

    /**
     * @test
     */
    public function eventListeners(): void
    {
        $actionMock = $this->createMock([Action::class, Subscribable::class]);

        $actionMock->method('preEvent')->willReturn('before.foo.bar');
        $actionMock->method('postEvent')->willReturn('after.foo.bar');

        $this->eventDispatcherMock->expects($this->at(0))->method('hasListeners')->with('before.foo.bar');
        $this->eventDispatcherMock->expects($this->at(1))->method('hasListeners')->with('after.foo.bar');

        $this->actionDispatcher->dispatch($actionMock, []);
    }
}
