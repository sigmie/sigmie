<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use ReflectionObject;
use Sigmie\AI\APIs\CohereEmbeddingsApi;
use Sigmie\AI\APIs\InfinityEmbeddingsApi;
use Sigmie\AI\APIs\OpenAIEmbeddingsApi;
use Sigmie\AI\APIs\VoyageEmbeddingsApi;
use Sigmie\AI\Contracts\Embedder;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\AI\Embedders\OpenAIProvider;
use Sigmie\AI\Embedders\VoyageProvider;
use Sigmie\Document\Document;
use Sigmie\Enums\CohereInputType;
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

        $this->assertTrue($this->sigmie->hasApi('api1'));
        $this->assertSame($api1, $this->sigmie->api('api1'));

        // Create properties with fields using different APIs
        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'api1');
        $props->text('description')->semantic(accuracy: 1, dimensions: 384, api: 'api2');
        $props->text('content')->semantic(accuracy: 1, dimensions: 384, api: 'api1');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $this->sigmie->collect($indexName, true)
            ->properties($props)
            ->merge([
                new Document([
                    'title' => 'Test Product',
                    'description' => 'A searchable test item',
                    'content' => 'Semantic routing payload',
                ], _id: 'test-product'),
            ]);

        // Create a search - this should populate VectorPools for each API
        $search = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->queryString('test query')
            ->disableKeywordSearch();

        // Execute the search to trigger vector generation
        $response = $search->get();

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
        $hits = $response->json('hits');

        $this->assertSame('test-product', $hits[0]['_id']);
        $this->assertSame('Test Product', $hits[0]['_source']['title']);
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

        $response = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->queryString('Product Title')
            ->disableKeywordSearch()
            ->get();

        $hits = $response->json('hits');

        $this->assertSame('test-doc', $hits[0]['_id']);
        $this->assertSame('Product Title', $hits[0]['_source']['title']);
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

        $response = $search->get();

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
        $hits = $response->json('hits');

        $this->assertSame('doc1', $hits[0]['_id']);
        $this->assertSame('Test Product', $hits[0]['_source']['title']);
    }

    /**
     * @test
     */
    public function real_embedding_apis_index_and_search_documents_through_elasticsearch(): void
    {
        foreach ($this->realEmbeddingApis() as $apiName => $api) {
            $indexName = uniqid();
            $registeredApiName = 'real-'.$apiName;

            $this->sigmie->registerApi($registeredApiName, $api);

            $this->assertNotSame('', $api->model());
            $this->assertGreaterThan(0, $api->maxBatchSize());
            $this->assertCount(3, $api->embed('accounting audit', 3));
            $this->assertCount(512, $api->embed('accounting audit', 512));
            $this->assertSame([], $api->batchEmbed([]));
            $this->assertCount(512, $api->batchEmbed([
                ['text' => 'accounting audit', 'dims' => 512],
            ])[0]['vector']);
            $this->assertCount(128, $api->batchEmbed([
                ['text' => 'accounting audit', 'dims' => 128],
            ])[0]['vector']);
            $this->assertSame(200, $api->promiseEmbed('accounting audit', 3)->wait()->getStatusCode());

            if ($api instanceof VoyageEmbeddingsApi) {
                $this->assertCount(3, $api->embedQuery('accounting audit', 3));
                $this->assertCount(512, $api->embedQuery('accounting audit', 512));
            }

            $props = new NewProperties;
            $props->text('title')->semantic(api: $registeredApiName, accuracy: 1, dimensions: 384);

            $this->sigmie->newIndex($indexName)->properties($props)->create();

            $this->sigmie->collect($indexName, true)
                ->properties($props)
                ->merge([
                    new Document(['title' => 'Accounting ledger reconciliation'], _id: $apiName.'-accounting'),
                    new Document(['title' => 'Basketball training schedule'], _id: $apiName.'-basketball'),
                ]);

            $response = $this->sigmie->newSearch($indexName)
                ->properties($props)
                ->semantic()
                ->disableKeywordSearch()
                ->queryString('accounting audit')
                ->get();

            $hits = $response->json('hits');

            $this->assertSame($apiName.'-accounting', $hits[0]['_id']);
            $this->assertSame('Accounting ledger reconciliation', $hits[0]['_source']['title']);
        }
    }

    /**
     * @test
     */
    public function legacy_embedding_providers_index_and_search_documents_through_elasticsearch(): void
    {
        foreach ($this->legacyEmbeddingProviders() as $providerName => $provider) {
            $indexName = uniqid();
            $registeredApiName = 'legacy-'.$providerName;
            $api = $this->embeddingApiFromProvider($provider);

            $this->sigmie->registerApi($registeredApiName, $api);

            $this->assertNotSame('', $provider->getModel());
            $this->assertCount(3, $provider->embed('accounting audit', 3));
            $this->assertSame([], $provider->batchEmbed([]));
            $this->assertCount(512, $provider->batchEmbed([
                ['text' => 'accounting audit', 'dims' => 512],
            ])[0]['vector']);

            $legacyPromise = $provider->promiseEmbed('accounting audit', 3);
            $this->assertContains($legacyPromise->getState(), ['pending', 'fulfilled']);
            $this->assertCount(3, $legacyPromise->wait()['_embeddings']);
            $this->assertCount(3, $provider->promiseEmbed('accounting audit', 3)->then()->wait()['_embeddings']);
            $this->assertCount(3, $provider->promiseEmbed('accounting audit', 3)->then(
                fn (array $response): array => $response['_embeddings']
            )->wait());

            $props = new NewProperties;
            $props->text('title')->semantic(api: $registeredApiName, accuracy: 1, dimensions: 384);

            $this->sigmie->newIndex($indexName)->properties($props)->create();

            $this->sigmie->collect($indexName, true)
                ->properties($props)
                ->merge([
                    new Document(['title' => 'Accounting ledger reconciliation'], _id: $providerName.'-accounting'),
                    new Document(['title' => 'Basketball training schedule'], _id: $providerName.'-basketball'),
                ]);

            $response = $this->sigmie->newSearch($indexName)
                ->properties($props)
                ->semantic()
                ->disableKeywordSearch()
                ->queryString('accounting audit')
                ->get();

            $hits = $response->json('hits');

            $this->assertSame($providerName.'-accounting', $hits[0]['_id']);
            $this->assertSame('Accounting ledger reconciliation', $hits[0]['_source']['title']);
        }
    }

    private function realEmbeddingApis(): array
    {
        $openAI = new OpenAIEmbeddingsApi('test-key');
        $cohere = new CohereEmbeddingsApi('test-key', CohereInputType::SearchDocument);
        $voyage = new VoyageEmbeddingsApi('test-key');

        return [
            'openai' => $this->withMockEmbeddingClient($openAI, 'openai'),
            'cohere' => $this->withMockEmbeddingClient($cohere, 'cohere'),
            'voyage' => $this->withMockEmbeddingClient($voyage, 'voyage'),
        ];
    }

    private function legacyEmbeddingProviders(): array
    {
        $openAI = new OpenAIProvider('test-key');
        $voyage = new VoyageProvider('test-key');

        return [
            'openai-provider' => $this->withMockEmbeddingClient($openAI, 'openai'),
            'voyage-provider' => $this->withMockEmbeddingClient($voyage, 'voyage'),
        ];
    }

    private function withMockEmbeddingClient(EmbeddingsApi|Embedder $api, string $provider): EmbeddingsApi|Embedder
    {
        $handler = function (RequestInterface $request) use ($provider): Promise {
            $payload = json_decode((string) $request->getBody(), true);
            $texts = $payload['input'] ?? $payload['texts'] ?? [];
            $texts = is_array($texts) ? $texts : [$texts];

            $dimensions = (int) ($payload['dimensions'] ?? $payload['output_dimension'] ?? 384);
            $vectors = array_map(fn (string $text): array => $this->test_vector($text, $dimensions), $texts);

            $promise = new Promise;
            $promise->resolve(new Response(200, [], json_encode(
                match ($provider) {
                    'cohere' => [
                        '_embeddings' => ['float' => $vectors],
                        'embeddings' => ['float' => $vectors],
                    ],
                    default => ['data' => array_map(fn (array $vector): array => ['embedding' => $vector], $vectors)],
                },
                JSON_THROW_ON_ERROR
            )));

            return $promise;
        };

        $reflection = new ReflectionObject($api);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($api, new Client(['handler' => $handler]));

        return $api;
    }

    private function embeddingApiFromProvider(Embedder $provider): EmbeddingsApi
    {
        return new class($provider) implements EmbeddingsApi
        {
            public function __construct(private Embedder $provider) {}

            public function embed(string $text, int $dimensions): array
            {
                return $this->provider->embed($text, $dimensions);
            }

            public function batchEmbed(array $payload): array
            {
                return $this->provider->batchEmbed($payload);
            }

            public function promiseEmbed(string $text, int $dimensions): Promise
            {
                $promise = new Promise;
                $promise->resolve($this->provider->embed($text, $dimensions));

                return $promise;
            }

            public function model(): string
            {
                return $this->provider->getModel();
            }

            public function maxBatchSize(): int
            {
                return 2048;
            }
        };
    }

    private function test_vector(string $text, int $dimensions): array
    {
        $dimensions = max(1, $dimensions);
        $vector = array_fill(0, $dimensions, 0.001);
        $tokens = array_values(array_filter(explode(' ', trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower($text))))));

        foreach ($tokens as $token) {
            $vector[crc32($token) % $dimensions] += 0.25;
        }

        foreach ($this->semanticGroups() as $index => $terms) {
            foreach ($terms as $term) {
                if (in_array($term, $tokens, true)) {
                    $vector[$index % $dimensions] += 4.0;
                }
            }
        }

        $magnitude = sqrt(array_sum(array_map(fn (float $value): float => $value * $value, $vector)));

        return array_map(fn (float $value): float => $value / $magnitude, $vector);
    }

    private function semanticGroups(): array
    {
        return [
            ['accounting', 'ledger', 'reconciliation', 'audit'],
            ['basketball', 'training', 'schedule'],
        ];
    }
}
