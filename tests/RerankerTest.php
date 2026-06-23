<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\Document\RerankedHit;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\Text;
use Sigmie\Semantic\Contracts\AIProvider;
use Sigmie\Semantic\Reranker;
use Sigmie\Search\NewRerank;
use Sigmie\Testing\TestCase;
use Symfony\Component\Yaml\Yaml;

class RerankerTest extends TestCase
{
    /**
     * @test
     */
    public function rerank_threshold(): void
    {
        $indexName = uniqid();
        $cohereReranker = $this->rerankApi;

        $blueprint = new NewProperties;
        $blueprint->longText('name')->semantic(dimensions: 384, api: 'test-embeddings');
        $blueprint->longText('description')->semantic(dimensions: 384, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['name' => 'PHP Framework', 'description' => 'Laravel is a PHP framework for web development']),
            new Document(['name' => 'JavaScript Library', 'description' => 'React is a JavaScript library for building user interfaces']),
            new Document(['name' => 'Python Framework', 'description' => 'Django is a Python framework for web development']),
        ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('web framework')
            ->semantic()
            ->get();

        $this->assertEquals(2, $res->total());

        // Assert payload contains only specified fields formatted as YAML
        $payload = NewRerank::documentPayloads($res->hits(), ['name', 'description']);

        $this->assertCount(2, $payload);
        foreach ($payload as $document) {
            $parsed = Yaml::parse($document);
            $this->assertArrayHasKey('name', $parsed);
            $this->assertArrayHasKey('description', $parsed);
            $this->assertCount(2, $parsed);
        }

        $rerankedHits = $res->rerank($cohereReranker, ['name', 'description'], 'web framework', 2);

        // Assert rerank API was called
        $this->rerankApi->assertRerankWasCalled();
        $this->rerankApi->assertRerankWasCalled(1);

        // Assert rerank was called with correct query
        $this->rerankApi->assertRerankWasCalledWith('web framework');

        // Assert rerank was called with correct query and topK
        $this->rerankApi->assertRerankWasCalledWith('web framework', 2);

        // Assert rerank was called with correct number of documents
        $this->rerankApi->assertRerankWasCalledWithDocumentCount(2);

        // Assert topK is respected (should return 2 hits)
        $this->assertCount(2, $rerankedHits);

        // Assert all returned hits are RerankedHit instances
        foreach ($rerankedHits as $hit) {
            $this->assertInstanceOf(RerankedHit::class, $hit);
            $this->assertIsFloat($hit->_rerank_score);
        }
    }

    /**
     * @test
     */
    public function semantic_reranker_reorders_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->longText('title')->semantic(dimensions: 384, api: 'test-embeddings');
        $blueprint->longText('description')->semantic(dimensions: 384, api: 'test-embeddings');

        $properties = $blueprint->get();

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Laravel', 'description' => 'PHP web framework']),
                new Document(['title' => 'React', 'description' => 'JavaScript interface library']),
                new Document(['title' => 'Django', 'description' => 'Python web framework']),
            ]);

        $response = $this->sigmie->newSearch($indexName)
            ->properties($properties)
            ->queryString('framework')
            ->fields(['title', 'description'])
            ->size(3)
            ->makeSearch()
            ->get();

        $this->assertSame(2, $response->total());

        $provider = $this->semanticProvider();

        $reranked = (new Reranker($provider, $properties, 0.5))
            ->rerank($response, 'best framework');

        $rawHits = $reranked->get()['hits']['hits'];

        $this->assertCount(2, $rawHits);
        $this->assertSame('Django', $rawHits[0]['_source']['title']);
        $this->assertSame('Laravel', $rawHits[1]['_source']['title']);
        $this->assertSame(0.93, $rawHits[0]['_rerank_score']);
        $this->assertCount(2, $provider->documents);
        $this->assertStringContainsString('title: Django', implode("\n", $provider->documents));
    }

    /**
     * @test
     */
    public function semantic_reranker_leaves_empty_elasticsearch_results_untouched(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->longText('title')->semantic(dimensions: 384, api: 'test-embeddings');

        $properties = $blueprint->get();

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $response = $this->sigmie->newSearch($indexName)
            ->properties($properties)
            ->queryString('missing')
            ->fields(['title'])
            ->makeSearch()
            ->get();

        $provider = $this->semanticProvider();

        $reranked = (new Reranker($provider, $properties))
            ->rerank($response, 'missing');

        $this->assertSame(0, $reranked->total());
        $this->assertSame([], $provider->documents);
    }

    /**
     * @test
     */
    public function semantic_reranker_leaves_blank_queries_untouched(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->longText('title')->semantic(dimensions: 384, api: 'test-embeddings');

        $properties = $blueprint->get();

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Laravel framework']),
            ]);

        $response = $this->sigmie->newSearch($indexName)
            ->properties($properties)
            ->queryString('Laravel')
            ->fields(['title'])
            ->makeSearch()
            ->get();

        $provider = $this->semanticProvider();

        $reranked = (new Reranker($provider, $properties))
            ->rerank($response, '  ');

        $this->assertSame(1, $reranked->total());
        $this->assertSame('Laravel framework', $reranked->get()['hits']['hits'][0]['_source']['title']);
        $this->assertSame([], $provider->documents);
    }

    /**
     * @test
     */
    public function semantic_reranker_formats_array_semantic_fields_from_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->longText('experience')->semantic(dimensions: 384, api: 'test-embeddings');

        $properties = $blueprint->get();

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document([
                    'experience' => [
                        'Staff Engineer at Sigmie from 2020 to 2024 in Search: Built Elasticsearch relevance',
                        'Developer at Acme from 2018 to 2020: Built Laravel APIs',
                    ],
                ]),
            ]);

        $response = $this->sigmie->newSearch($indexName)
            ->properties($properties)
            ->queryString('Elasticsearch')
            ->fields(['experience'])
            ->makeSearch()
            ->get();

        $provider = $this->semanticProvider([0.9]);

        $reranked = (new Reranker($provider, $properties))
            ->rerank($response, 'search relevance');

        $this->assertSame(1, $reranked->total());
        $this->assertSame(0.9, $reranked->get()['hits']['hits'][0]['_rerank_score']);
        $this->assertStringContainsString(
            'Staff Engineer at Sigmie from 2020 to 2024: Built Elasticsearch relevance',
            $provider->documents[0]
        );
        $this->assertStringContainsString(
            'Developer at Acme from 2018 to 2020: Built Laravel APIs',
            $provider->documents[0]
        );
    }

    protected function semanticProvider(array $scores = []): object
    {
        return new class($scores) implements AIProvider
        {
            public array $documents = [];

            public function __construct(protected array $scores = []) {}

            public function embed(string $text, Text $originalType): array
            {
                return [];
            }

            public function batchEmbed(array $payload): array
            {
                return [];
            }

            public function type(Text $originalType): Type
            {
                return $originalType;
            }

            public function queries(array|string $text, Text $originalType): array
            {
                return [];
            }

            public function rerank(array $documents, string $queryString): array
            {
                $this->documents = $documents;

                if ($this->scores !== []) {
                    return $this->scores;
                }

                return array_map(fn (string $document): float => match (true) {
                    str_contains($document, 'Django') => 0.93,
                    str_contains($document, 'Laravel') => 0.81,
                    default => 0.12,
                }, $documents);
            }
        };
    }
}
