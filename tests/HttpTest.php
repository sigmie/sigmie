<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Testing\TestCase;
use Sigmie\Http\JSONResponse;
use Sigmie\Http\JSONRequest;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response as PsrResponse;
use GuzzleHttp\Psr7\Uri;
use Sigmie\Http\NdJSONRequest;

class HttpTest extends TestCase
{
    /**
     * @test
     */
    public function response(): void
    {
        $successfulResponse = new JSONResponse(new PsrResponse(200, ['content-type' => 'application/json'], '{"foo":"bar"}'));

        $this->assertEquals(200, $successfulResponse->code());
        $this->assertEquals(['foo' => 'bar'], $successfulResponse->json());
        $this->assertEquals('bar', $successfulResponse->json('foo'));
        $this->assertInstanceOf(ResponseInterface::class, $successfulResponse->psr());
        $this->assertEquals('{"foo":"bar"}', (string) $successfulResponse);
        $this->assertEquals('{"foo":"bar"}', $successfulResponse->body());
        $this->assertEquals('application/json', $successfulResponse->header('content-type'));

        $errorResponse = new JSONResponse(new PsrResponse(500, ['content-type' => 'application/json'], '{"foo":"bar"}'));

        $this->assertEquals(true, $errorResponse->failed());
        $this->assertEquals(false, $errorResponse->clientError());
    }

    /**
     * @test
     */
    public function json_request_has_correct_content_type_header(): void
    {
        $req = new JSONRequest('GET', new Uri('http://foo.com'), ['foo' => 'bar']);

        $this->assertContains('application/json', $req->getHeader('Content-type'));
    }

    /**
     * @test
     */
    public function decode_ndjson()
    {
        $req = new NdJSONRequest('GET', new Uri('http://foo.com'), [
            ['foo' => 'bar'],
            ['foo' => 'baz'],
        ]);

        $this->assertEquals([['foo' => 'bar'], ['foo' => 'baz'], null], $req->body());
    }

    /**
     * @test
     */
    public function json_requests_accept_null_body(): void
    {
        $ndJsonReq = new NdJSONRequest('GET', new Uri('http://foo.com'), null);
        $jsonReq = new JSONRequest('GET', new Uri('http://foo.com'), null);

        $this->assertEquals('', $ndJsonReq->getBody()->getContents());
        $this->assertEquals('', $jsonReq->getBody()->getContents());
    }

    /**
     * @test
     */
    public function nd_request_has_correct_content_type_header_and_new_line_delimiter(): void
    {
        $req = new NdJSONRequest('GET', new Uri('http://foo.com'), [['foo' => 'bar'], ['foo' => 'baz']]);

        $this->assertContains('application/x-ndjson', $req->getHeader('Content-type'));
        $this->assertEquals("{\"foo\":\"bar\"}\n{\"foo\":\"baz\"}\n", (string) $req->getBody());
    }
}
