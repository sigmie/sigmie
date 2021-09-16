<?php

declare(strict_types=1);

namespace Sigmie\Base\Tests\Http;

use GuzzleHttp\Psr7\Response;
use Sigmie\Base\Exceptions\FailedToBuildSynonyms;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Testing\TestCase;

class ElasticsearchResponseTest extends TestCase
{
    /**
     * @test
     */
    public function failed_to_build_synonyms_exception_is_thrown()
    {
        $this->expectException(FailedToBuildSynonyms::class);

        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->withoutMappings()
            ->stopwords(['foo'])
            ->twoWaySynonyms([
                ['foo', 'bar'],
                ['friend', 'buddy', 'partner']
            ])
            ->create();
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
