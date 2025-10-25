<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\AI\APIs\InfinityEmbeddingsApi;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Testing\FakeEmbeddingsApi;
use Sigmie\Testing\TestCase;

class AiApisTest extends TestCase
{
    /**
     * @test
     */
    public function multiple_apis_are_isolated_in_search(): void
    {
        $indexName = uniqid();

        // Create fake APIs using the existing FakeEmbeddingsApi wrapper
        $embeddingUrl = getenv('LOCAL_EMBEDDING_URL') ?: 'http://localhost:7997';
        $api1 = new FakeEmbeddingsApi(new InfinityEmbeddingsApi($embeddingUrl));
        $api2 = new FakeEmbeddingsApi(new InfinityEmbeddingsApi($embeddingUrl));

        // Register both APIs
        $this->sigmie->registerApi('api1', $api1);
        $this->sigmie->registerApi('api2', $api2);

        // Create properties with fields using different APIs
        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'api1');
        $props->text('description')->semantic(accuracy: 1, dimensions: 384, api: 'api2');
        $props->text('content')->semantic(accuracy: 1, dimensions: 384, api: 'api1');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        // Create a search - this should populate VectorPools for each API
        $search = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->queryString('test query')
            ->disableKeywordSearch();

        // Execute the search to trigger vector generation
        $search->get();

        // Verify that api1 was called for title and content
        $api1->assertBatchEmbedWasCalled();

        $api1Calls = $api1->getBatchEmbedCalls();
        $api1TextsCalled = [];
        $api1Dims = [];

        foreach ($api1Calls as $payload) {
            foreach ($payload as $item) {
                $api1TextsCalled[] = $item['text'] ?? '';
                $api1Dims[] = $item['dims'] ?? 0;
            }
        }

        // api1 should have been called with 'test query' for 384 dimensions
        $this->assertContains('test query', $api1TextsCalled, 'API1 should be called with the query string');
        $this->assertContains(384, $api1Dims, 'API1 should be called with 384 dimensions for title and content fields');

        // Verify that api2 was called for description
        $api2->assertBatchEmbedWasCalled();

        $api2Calls = $api2->getBatchEmbedCalls();
        $api2TextsCalled = [];
        $api2Dims = [];

        foreach ($api2Calls as $payload) {
            foreach ($payload as $item) {
                $api2TextsCalled[] = $item['text'] ?? '';
                $api2Dims[] = $item['dims'] ?? 0;
            }
        }

        $this->assertContains('test query', $api2TextsCalled, 'API2 should be called with the query string');
        $this->assertContains(384, $api2Dims, 'API2 should be called with 384 dimensions for description field');
    }

    /**
     * @test
     */
    public function document_processor_uses_correct_apis_for_each_field(): void
    {
        $indexName = uniqid();

        // Create fake APIs
        $embeddingUrl = getenv('LOCAL_EMBEDDING_URL') ?: 'http://localhost:7997';
        $openaiApi = new FakeEmbeddingsApi(new InfinityEmbeddingsApi($embeddingUrl));
        $cohereApi = new FakeEmbeddingsApi(new InfinityEmbeddingsApi($embeddingUrl));

        // Register APIs
        $this->sigmie->registerApi('openai-embeddings', $openaiApi);
        $this->sigmie->registerApi('cohere-embeddings', $cohereApi);

        // Create properties with mixed API usage
        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'openai-embeddings');
        $props->text('summary')->semantic(accuracy: 1, dimensions: 384, api: 'cohere-embeddings');
        $props->nested('reviews', function (NewProperties $props): void {
            $props->text('comment')->semantic(accuracy: 1, dimensions: 384, api: 'openai-embeddings');
            $props->text('sentiment')->semantic(accuracy: 1, dimensions: 384, api: 'cohere-embeddings');
        });

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $this->sigmie->collect($indexName, true)->properties($props);

        // Add a document - this should trigger DocumentProcessor
        $collected->merge([
            new Document([
                'title' => 'Product Title',
                'summary' => 'Product Summary',
                'reviews' => [
                    ['comment' => 'Great product!', 'sentiment' => 'positive'],
                    ['comment' => 'Could be better', 'sentiment' => 'neutral'],
                ],
            ], _id: 'test-doc'),
        ]);

        // Verify OpenAI API was called for title and review comments
        $openaiApi->assertBatchEmbedWasCalled();

        $openaiCalls = $openaiApi->getBatchEmbedCalls();
        $openaiTexts = [];

        foreach ($openaiCalls as $payload) {
            foreach ($payload as $item) {
                $openaiTexts[] = $item['text'] ?? '';
            }
        }

        $this->assertContains('Product Title', $openaiTexts, 'OpenAI should process the title');
        $this->assertContains('Great product! Could be better', $openaiTexts, 'OpenAI should process concatenated review comments');

        // Verify Cohere API was called for summary and sentiments
        $cohereApi->assertBatchEmbedWasCalled();

        $cohereCalls = $cohereApi->getBatchEmbedCalls();
        $cohereTexts = [];

        foreach ($cohereCalls as $payload) {
            foreach ($payload as $item) {
                $cohereTexts[] = $item['text'] ?? '';
            }
        }

        $this->assertContains('Product Summary', $cohereTexts, 'Cohere should process the summary');
        $this->assertContains('positive neutral', $cohereTexts, 'Cohere should process concatenated sentiments');
    }

    /**
     * @test
     */
    public function separate_api_for_indexing_and_searching(): void
    {
        $indexName = uniqid();

        // Create two separate fake APIs
        $embeddingUrl = getenv('LOCAL_EMBEDDING_URL') ?: 'http://localhost:7997';
        $indexApi = new FakeEmbeddingsApi(new InfinityEmbeddingsApi($embeddingUrl));
        $searchApi = new FakeEmbeddingsApi(new InfinityEmbeddingsApi($embeddingUrl));

        // Register both APIs
        $this->sigmie->registerApi('index-api', $indexApi);
        $this->sigmie->registerApi('search-api', $searchApi);

        // Create properties with api for indexing and searchApi for searching
        $props = new NewProperties;
        $props->text('title')
            ->semantic(accuracy: 1, dimensions: 384, api: 'index-api')
            ->searchApi('search-api');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        // Reset call tracking before indexing
        $indexApi->reset();
        $searchApi->reset();

        // Index a document - should use index-api
        $this->sigmie->collect($indexName, true)
            ->properties($props)
            ->merge([
                new Document(['title' => 'Test Product'], _id: 'doc1'),
            ]);

        // Assert index-api was called during indexing
        $indexApi->assertBatchEmbedWasCalled();
        $indexApiCalls = $indexApi->getBatchEmbedCalls();
        $indexTexts = [];

        foreach ($indexApiCalls as $payload) {
            foreach ($payload as $item) {
                $indexTexts[] = $item['text'] ?? '';
            }
        }

        $this->assertContains('Test Product', $indexTexts, 'index-api should be called during document indexing');

        // Assert search-api was NOT called during indexing
        $this->assertCount(0, $searchApi->getBatchEmbedCalls(), 'search-api should NOT be called during indexing');

        // Reset call tracking before searching
        $indexApi->reset();
        $searchApi->reset();

        // Perform search - should use search-api
        $search = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->queryString('test query')
            ->disableKeywordSearch();

        $search->get();

        // Assert search-api was called during search
        $searchApi->assertBatchEmbedWasCalled();
        $searchApiCalls = $searchApi->getBatchEmbedCalls();
        $searchTexts = [];

        foreach ($searchApiCalls as $payload) {
            foreach ($payload as $item) {
                $searchTexts[] = $item['text'] ?? '';
            }
        }

        $this->assertContains('test query', $searchTexts, 'search-api should be called during search');

        // Assert index-api was NOT called during search
        $this->assertCount(0, $indexApi->getBatchEmbedCalls(), 'index-api should NOT be called during search');
    }
}
