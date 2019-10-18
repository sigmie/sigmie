<?php

namespace Sigma\Test\Unit\Manager;

use PHPUnit\Framework\TestCase;
use Sigma\Manager\ManagerBuilder;
use Elasticsearch\Client as Elasticsearch;
use Sigma\Contract\ActionDispatcher;
use Sigma\ResponseHandler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as EventDispatcher;

class ManagerBuilderTest extends TestCase
{
    private $esMock;

    /**
     * @var ManagerBuilder
     */
    private $builder;

    public function setUp(): void
    {
        $this->esMock = $this->createMock(Elasticsearch::class);

        $this->builder = new ManagerBuilder($this->esMock);
    }

    /**
     * @test
     */
    public function responseHandler(): void
    {
        $handlerMock = $this->createMock(ResponseHandler::class);

        $this->builder->setResponseHandler($handlerMock);

        $handler = $this->builder->getResponseHandler();

        $this->assertEquals($handlerMock, $handler);
    }

    /**
     * @test
     */
    public function actionDispatcher(): void
    {
        $dispatcherMock = $this->createMock(ActionDispatcher::class);

        $this->builder->setActionDispatcher($dispatcherMock);

        $dispatcher = $this->builder->getActionDispatcher();

        $this->assertEquals($dispatcherMock, $dispatcher);
    }

    /**
     * @test
     */
    public function eventDispatcher(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcher::class);

        $this->builder->setEventDispatcher($eventDispatcherMock);

        $dispatcher = $this->builder->getEventDispatcher();

        $this->assertEquals($eventDispatcherMock, $dispatcher);
    }

    /**
     * @test
     */
    public function defaultInstances(): void
    {
        $this->builder->build();

        $actionDispatcher = $this->builder->getActionDispatcher();
        $eventDispatcher = $this->builder->getEventDispatcher();
        $responseHandler = $this->builder->getResponseHandler();

        $this->assertInstanceOf(ActionDispatcher::class, $actionDispatcher);
        $this->assertInstanceOf(EventDispatcher::class, $eventDispatcher);
        $this->assertInstanceOf(ResponseHandler::class, $responseHandler);
    }

    /**
     * @test
     */
    public function buildInstances(): void
    {
        $actionDispatcherMock = $this->createMock(ActionDispatcher::class);
        $responseHandlerMock = $this->createMock(ResponseHandler::class);

        $this->builder->setActionDispatcher($actionDispatcherMock);
        $this->builder->setResponseHandler($responseHandlerMock);

        $this->builder->build();

        $this->assertEquals($actionDispatcherMock, $this->builder->getActionDispatcher());
        $this->assertEquals($responseHandlerMock, $this->builder->getResponseHandler());
    }
}
