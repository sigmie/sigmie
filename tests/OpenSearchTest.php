<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\Enums\SearchEngine;
use Sigmie\Mappings\NewProperties;
use Sigmie\Sigmie;
use Sigmie\Testing\TestCase as BaseTestCase;

class OpenSearchTest extends BaseTestCase
{
    public function setUp(): void
    {
        // Skip parent setup to avoid clearing Elasticsearch
        // We'll set up OpenSearch instead
        \PHPUnit\Framework\TestCase::setUp();

        // Load environment
        $this->loadEnv();

        // Create OpenSearch client
        $this->jsonClient = \Sigmie\Http\JSONClient::create(['https://localhost:9200'], [
            'auth' => ['admin', 'MyStrongPass123!@#'],
            'verify' => false,
        ]);

        $this->elasticsearchConnection = new \Sigmie\Base\Http\ElasticsearchConnection($this->jsonClient);

        // Detect search engine
        $this->detectSearchEngine();

        // Clear OpenSearch instead of Elasticsearch
        $this->clearElasticsearch($this->elasticsearchConnection);

        $this->setElasticsearchConnection($this->elasticsearchConnection);

        // Set up fake APIs
        $this->embeddingApi = new \Sigmie\Testing\FakeEmbeddingsApi(
            new \Sigmie\AI\APIs\InfinityEmbeddingsApi('http://localhost:7997')
        );
        $this->rerankApi = new \Sigmie\Testing\FakeRerankApi(
            new \Sigmie\AI\APIs\InfinityRerankApi('http://localhost:7998')
        );
        $this->llmApi = new \Sigmie\Testing\FakeLLMApi(
            new \Sigmie\AI\APIs\OllamaApi('http://localhost:7999')
        );
        $this->clipApi = new \Sigmie\Testing\FakeClipApi(
            new \Sigmie\AI\APIs\InfinityClipApi('http://localhost:7996', 'wkcn/TinyCLIP-ViT-8M-16-Text-3M-YFCC15M')
        );

        // Create Sigmie instance
        $this->sigmie = new Sigmie($this->elasticsearchConnection);
        $this->sigmie->registerApi('test-embeddings', $this->embeddingApi);
        $this->sigmie->registerApi('test-rerank', $this->rerankApi);
        $this->sigmie->registerApi('test-llm', $this->llmApi);
        $this->sigmie->registerApi('test-clip', $this->clipApi);
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * @test
     */
    public function opensearch_connection()
    {
        $connected = $this->sigmie->isConnected();

        $this->assertTrue($connected);
    }

    /**
     * @test
     */
    public function create_index_with_text_field()
    {
        $indexName = uniqid('os_');

        $blueprint = new NewProperties();
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->index($indexName);

        $this->assertNotNull($index);

        $this->sigmie->delete($indexName);
    }

    /**
     * @test
     */
    public function create_semantic_index()
    {
        $indexName = uniqid('os_');

        $blueprint = new NewProperties();
        $blueprint->text('job_description')->semantic(accuracy: 1, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->index($indexName);

        $this->assertNotNull($index);

        // Verify the knn_vector field was created with OpenSearch format
        $raw = $index->raw;
        $jobDescriptionField = $raw['mappings']['properties']['embeddings']['properties']['job_description']['properties'] ?? [];

        $this->assertNotEmpty($jobDescriptionField);

        // Get the first vector field
        $vectorField = array_values($jobDescriptionField)[0] ?? [];

        // OpenSearch uses 'knn_vector' type instead of 'dense_vector'
        $this->assertEquals('knn_vector', $vectorField['type']);

        // OpenSearch uses 'dimension' instead of 'dims'
        $this->assertEquals(256, $vectorField['dimension']);

        // OpenSearch uses 'method' with HNSW parameters
        $this->assertArrayHasKey('method', $vectorField);
        $this->assertEquals('hnsw', $vectorField['method']['name']);
        $this->assertArrayHasKey('parameters', $vectorField['method']);
        $this->assertArrayHasKey('m', $vectorField['method']['parameters']);
        $this->assertArrayHasKey('ef_construction', $vectorField['method']['parameters']);

        $this->sigmie->delete($indexName);
    }

    /**
     * @test
     */
    public function semantic_search()
    {
        $indexName = uniqid('os_');

        $blueprint = new NewProperties();
        $blueprint->text('description')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['description' => 'A red sports car']),
                new Document(['description' => 'A blue bicycle']),
                new Document(['description' => 'A green truck']),
            ]);

        $response = $this->sigmie
            ->newSearch($indexName)
            ->properties($blueprint)
            ->semantic()
            ->queryString('automobile')
            ->get();

        // Should find at least the car and truck
        $this->assertGreaterThanOrEqual(1, $response->total());

        $this->sigmie->delete($indexName);
    }

    /**
     * @test
     */
    public function range_query()
    {
        $indexName = uniqid('os_');

        $blueprint = new NewProperties();
        $blueprint->range('numbers')->integer()
            ->withQueries(function (string $queryString) {
                return [
                    new \Sigmie\Query\Queries\Term\Range('numbers', [
                        '>=' => $queryString,
                        '<=' => $queryString,
                    ]),
                ];
            });

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document([
                    'numbers' => ['gt' => 10, 'lt' => 20],
                ]),
                new Document([
                    'numbers' => ['gt' => 21, 'lt' => 31],
                ]),
            ]);

        $response = $this->sigmie
            ->newSearch($indexName)
            ->noResultsOnEmptySearch()
            ->properties($blueprint)
            ->queryString('lorem')
            ->get();

        $this->assertEquals(0, $response->total());

        $response = $this->sigmie
            ->newSearch($indexName)
            ->noResultsOnEmptySearch()
            ->properties($blueprint)
            ->queryString('15')
            ->get();

        $this->assertEquals(1, $response->total());

        $this->sigmie->delete($indexName);
    }
}
