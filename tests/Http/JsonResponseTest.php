<?php

declare(strict_types=1);

namespace Sigmie\Tests\Http;

use GuzzleHttp\Psr7\Response as PsrResponse;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Sigmie\Http\JsonResponse;

class JsonResponseTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @test
     */
    public function response(): void
    {
        $successfulResponse = new JsonResponse(new PsrResponse(200, ['content-type' => 'application/json'], '{"foo":"bar"}'));

        $this->assertEquals(200, $successfulResponse->code());
        $this->assertEquals(['foo' => 'bar'], $successfulResponse->json());
        $this->assertEquals('bar', $successfulResponse->json('foo'));
        $this->assertInstanceOf(ResponseInterface::class, $successfulResponse->psr());
        $this->assertEquals('{"foo":"bar"}', (string) $successfulResponse);
        $this->assertEquals('{"foo":"bar"}', $successfulResponse->body());
        $this->assertEquals('application/json', $successfulResponse->header('content-type'));

        $errorResponse = new JsonResponse(new PsrResponse(500, ['content-type' => 'application/json'], '{"foo":"bar"}'));

        $this->assertEquals(true, $errorResponse->failed());
        $this->assertEquals(false, $errorResponse->clientError());
    }
}
