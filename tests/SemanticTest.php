<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\SigmieIndex; use Sigmie\Mappings\NewProperties; use Sigmie\Semantic\Embeddings\Openai;
use Sigmie\Testing\TestCase;
use Symfony\Component\Dotenv\Dotenv;

class SemanticTest extends TestCase
{
    /**
     * @before
     */
    public function loadEnv()
    {
        $dotenv = new Dotenv();
        $dotenv->usePutenv(true);
        $dotenv->loadEnv(__DIR__ . '/../.env', overrideExistingVars: true);
    }

    /**
     * @test
     */
    public function openai_search()
    {
        $openai = new Openai(
            getenv('OPENAI_API_KEY'),
            model: 'text-embedding-3-large',
            dims: 384
        );

        $testIndex = new class($this->sigmie) extends SigmieIndex {

            protected string $name;

            public function init(): void
            {
                $this->name = uniqid();
            }

            public function name(): string
            {
                return $this->name;
            }

            public function properties(): NewProperties
            {
                $blueprint = new NewProperties();
                $blueprint->title()->semantic();

                return $blueprint;
            }
        };

        // NOTE: Use larger models for larger texts
        // $openai = new SigmieEmbeddings();
        // $res = $openai->embeddings('Hello');

        $testIndex->newIndex()->create();

        $testIndex->collect()->merge([
            new Document([
                'name' => 'King',
            ]),
            new Document([
                'name' => 'Queen',
            ]),
        ]);

        $response = $testIndex->newSearch()
            ->noResultsOnEmptySearch()
            ->get();

        $hits = $response->json('hits.hits');

        $this->assertEquals('King', $hits[0]['_source']['name']);
    }

    /**
     * @test
     */
    public function nested_semantic_search()
    {
        //TODO
    }

    /**
     * @test
     */
    public function noop_provider()
    {
        //TODO
    }

    /**
     * @test
     */
    public function index_template()
    {
        //TODO
    }

    /**
     * @test
     */
    public function semantic_search_basic()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->title('name')->semantic();

        $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document([
                    'name' => 'King',
                ]),
                new Document([
                    'name' => 'Queen',
                ]),
            ]);

        $response = $this->sigmie
            ->newSearch($indexName)
            ->noResultsOnEmptySearch()
            ->properties($blueprint)
            ->queryString('woman')
            ->get();

        $hits = $response->json('hits.hits');

        $this->assertEquals('Queen', $hits[0]['_source']['name'] ?? null);

        $response = $this->sigmie
            ->newSearch($indexName)
            ->noResultsOnEmptySearch()
            ->properties($blueprint)
            ->queryString('king')
            ->get();

        $hits = $response->json('hits.hits');

        $this->assertEquals('King', $hits[0]['_source']['name'] ?? null);
    }
}
