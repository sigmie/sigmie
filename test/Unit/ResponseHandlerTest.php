<?php

namespace Sigma\Test\Unit;

use PHPUnit\Framework\TestCase;
use Sigma\ActionDispatcher;
use Sigma\Contract\Response;
use Sigma\ResponseHandler;

class ResponseHandlerTest extends TestCase
{
    /**
     * @var SigmaResponseHandler
     */
    private $handler;

    public function setUp(): void
    {
        /** @var  ActionDispatcher $actionDispatcherMock */
        $actionDispatcherMock = $this->createMock(ActionDispatcher::class);

        $this->handler = new ResponseHandler($actionDispatcherMock);
    }
    /**
     * @test
     */
    public function handle(): void
    {
        $response = $this->createMock(Response::class);

        $response->expects($this->once())->method('result')->with(['foo']);

        $this->handler->handle(['foo'], $response);
    }
}
