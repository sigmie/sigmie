<?php

declare(strict_types=1);

namespace Sigmie\Tests\Http;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use function Sigmie\Helpers\testing_host;
use Sigmie\Http\JSONClient;

use Sigmie\Http\JSONRequest;

class JsonClientTest extends TestCase
{
    /**
     * @var JSONClient
     */
    private $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = JSONClient::create(testing_host());
    }

    /**
     * @test
     */
    public function request(): void
    {
        $res = $this->client->request(new JSONRequest('GET', new Uri('/')));

        $this->assertEquals('You Know, for Search', $res->json('tagline'));
    }

    /**
     * @test
     */
    public function doesnt_throw_on_http_errors()
    {
        $res = $this->client->request(new JSONRequest('GET', new Uri('/unknown-index')));

        $this->assertEquals(404, $res->code());
    }
}
