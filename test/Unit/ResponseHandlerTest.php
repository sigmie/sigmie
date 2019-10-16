<?php

use PHPUnit\Framework\TestCase;
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
        $this->handler = new ResponseHandler();
    }
    /**
     * @test
     */
    public function handle(): void
    {
        $response = $responseMock = $responseMock = $this->createMock(Response::class);;

        $response->expects($this->once())->method('result')->with(['foo']);

        $this->handler->handle(['foo'], $response);
    }
}
