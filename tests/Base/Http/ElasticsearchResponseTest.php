<?php

declare(strict_types=1);

namespace Sigmie\Base\Tests\Http;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Sigmie\Base\Http\ElasticsearchResponse;

class ElasticsearchResponseTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @test
     */
    public function has_failed_if_response_has_error_key(): void
    {
        $psrResponse = new Response(200, [], '{"error":[]}');

        $esResponse = new ElasticsearchResponse($psrResponse);

        $this->assertTrue($esResponse->failed());
    }
}
