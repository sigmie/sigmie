<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Search\Autocomplete\NewPipeline;
use Sigmie\Search\Autocomplete\Script;
use Sigmie\Search\Autocomplete\Set;
use Sigmie\Testing\TestCase;

class AutocompletePipelineTest extends TestCase
{
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
