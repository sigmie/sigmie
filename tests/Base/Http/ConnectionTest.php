<?php

declare(strict_types=1);

namespace Sigmie\Base\Tests\Http;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Exceptions\ElasticsearchException;
use Sigmie\Base\Http\Connection;
use Sigmie\Base\Http\ElasticsearchRequest;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Base\Index\Mappings;
use Sigmie\Base\Index\Settings;
use function Sigmie\Helpers\testing_host;
use Sigmie\Http\JSONClient;

use Sigmie\Testing\TestCase;

class ConnectionTest extends TestCase
{
    /**
     * @test
     */
    public function throws_elasticsearch_exception()
    {
        $indexName = uniqid();

        $this->expectException(ElasticsearchException::class);

        $this->createIndex($indexName, new Settings, new Mappings);
        $this->createIndex($indexName, new Settings, new Mappings);
    }

    /**
     * @test
     */
    public function es_request_class(): void
    {
        $request = new Connection(JSONClient::create(testing_host()));

        $res = $request(new ElasticsearchRequest('GET', new Uri('/')));

        $this->assertInstanceOf(ElasticsearchResponse::class, $res);
        $this->assertFalse($res->failed());
    }
}
