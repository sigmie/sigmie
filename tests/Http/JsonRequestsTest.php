<?php

declare(strict_types=1);

namespace Sigmie\Tests\Http;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Sigmie\Http\JSONRequest;
use Sigmie\Http\NdJSONRequest;

class JSONRequestsTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
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
