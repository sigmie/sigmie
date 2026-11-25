<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Search\Autocomplete\NewPipeline;
use Sigmie\Search\Autocomplete\Script;
use Sigmie\Search\Autocomplete\Set;
use Sigmie\Testing\TestCase;

class AutocompletePipelineTest extends TestCase
{
    public function test_simulating_filtered_documents_preserves_their_order(): void
    {
        $pipeline = (new NewPipeline($this->elasticsearchConnection, uniqid('audit_')))->create();
        $docs = [1 => ['_source' => ['title' => 'First']], 3 => ['_source' => ['title' => 'Next']]];

        $response = $pipeline->simulate($docs);
        $this->assertSame('First', $response->json('docs.0.doc._source.title'));
        $this->assertSame('Next', $response->json('docs.1.doc._source.title'));
    }

    public function test_pipeline_id_containing_a_slash(): void
    {
        $pipeline = (new NewPipeline($this->elasticsearchConnection, uniqid('audit_').'/titles'))
            ->create();

        $response = $pipeline->simulate([['_source' => ['title' => 'Star']]]);
        $this->assertSame('Star', $response->json('docs.0.doc._source.title'));
    }

    /**
     * @test
     */
    public function created_pipeline_simulates_set_and_script_processors_in_elasticsearch(): void
    {
        $pipeline = (new NewPipeline($this->elasticsearchConnection, uniqid('autocomplete_')))
            ->description('Autocomplete enrichment pipeline')
            ->addPocessor(
                (new Set)
                    ->field('status')
                    ->value('indexed')
            )
            ->addPocessor(
                (new Script)
                    ->source('ctx.autocomplete = ctx.title + params.suffix')
                    ->params(['suffix' => ' search'])
            )
            ->create();

        $response = $pipeline->simulate([
            [
                '_source' => [
                    'title' => 'Laravel Scout',
                ],
            ],
        ]);

        $source = $response->json('docs.0.doc._source');

        $this->assertEquals('indexed', $source['status'] ?? null);
        $this->assertEquals('Laravel Scout search', $source['autocomplete'] ?? null);
    }
}
