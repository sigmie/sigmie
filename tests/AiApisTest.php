<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectionException;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Response;
use Http\Promise\Promise as HttpPromise;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\RequestInterface;
use ReflectionObject;
use ReflectionProperty;
use Sigmie\AI\APIs\ApiResponse;
use Sigmie\AI\APIs\CohereEmbeddingsApi;
use Sigmie\AI\APIs\CohereRerankApi;
use Sigmie\AI\APIs\InfinityClipApi;
use Sigmie\AI\APIs\InfinityEmbeddingsApi;
use Sigmie\AI\APIs\InfinityRerankApi;
use Sigmie\AI\APIs\JinaClipApi;
use Sigmie\AI\APIs\OpenAIEmbeddingsApi;
use Sigmie\AI\APIs\VoyageEmbeddingsApi;
use Sigmie\AI\APIs\VoyageRerankApi;
use Sigmie\AI\Contracts\Embedder;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\AI\Contracts\RerankApi;
use Sigmie\AI\Embedders\EmbeddingPromise;
use Sigmie\AI\Embedders\OpenAIProvider;
use Sigmie\AI\Embedders\VoyageProvider;
use Sigmie\AI\HandlesToolErrors;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Classification\NewClassification;
use Sigmie\Document\Document;
use Sigmie\Enums\CohereInputType;
use Sigmie\Mappings\NewProperties;
use Sigmie\Search\VectorPool;
use Sigmie\Testing\FakeEmbeddingsApi;
use Sigmie\Testing\FakeRerankApi;
use Sigmie\Testing\TestCase;
use Throwable;
use TypeError;
use UnexpectedValueException;

use function Sigmie\Functions\batch_slots;

class AiApisTest extends TestCase
{
    /** @dataProvider rerankAdapters */
    public function test_rerank_adapters_send_structured_document_text(RerankApi $api, string $field): void
    {
        $requests = [];
        $this->mockAdapterResponses($api, $requests, [new Response(200, [], json_encode([$field => []]))]);
        $api->rerank([7 => ['text' => 'running shoes'], 9 => 'boots'], 'shoes');

        $this->assertSame(['running shoes', 'boots'], $requests[0]['documents']);
    }

    public function test_batch_dimensions_match_the_fake_request(): void
    {
        $api = new CohereEmbeddingsApi('test', CohereInputType::SearchDocument, 'embed-v4.0');
        $requests = [];
        $this->recordAdapterRequests($api, $requests);
        $payload = [['text' => 'guide', 'dimensions' => 256]];

        $this->assertCount(256, (new FakeEmbeddingsApi($api))->batchEmbed($payload)[0]['vector']);
        $this->assertCount(256, $api->batchEmbed($payload)[0]['vector']);
        $this->assertSame(256, $requests[0]['output_dimension']);
    }

    public function test_cohere_search_pool_requests_query_embeddings(): void
    {
        $api = new CohereEmbeddingsApi('test', CohereInputType::SearchDocument);
        $requests = [];
        $this->recordAdapterRequests($api, $requests);
        $pool = new VectorPool($api);
        $pool->get('first', 2);
        $pool->getMany([['text' => 'second', 'dims' => 2]]);

        $api->embed('document', 2);

        $this->assertSame(['search_query', 'search_query', 'search_document'], array_column($requests, 'input_type'));
    }

    public function test_classification_accepts_fixed_width_embeddings(): void
    {
        $api = new OpenAIEmbeddingsApi('test', 'text-embedding-ada-002');
        $body = json_encode(['data' => [['index' => 0, 'embedding' => array_fill(0, 1536, 1)]]], JSON_THROW_ON_ERROR);
        $handler = new MockHandler([new Response(200, [], $body), new Response(200, [], $body)]);
        (new ReflectionProperty($api, 'client'))->setValue($api, new Client(['handler' => $handler]));

        $result = (new NewClassification($api))->examples([['text' => 'cat', 'label' => 'pet']])
            ->input('kitten')->classify();

        $this->assertSame(1.0, $result->score('pet'));
    }

    public function test_fake_rerank_scores_fit_the_provider_range(): void
    {
        $api = new FakeRerankApi(new CohereRerankApi('test'));
        [$first, $second] = $api->rerank(['red book', 'red'], 'red book');

        $this->assertLessThanOrEqual(1.0, $first['score']);
        $this->assertGreaterThan($second['score'], $first['score']);
    }

    public function test_embedding_response_preserves_input_order_when_iterated(): void
    {
        $response = new Response(200, [], '{"data":[{"index":1,"embedding":[2]},{"index":0,"embedding":[1]}]}');

        $this->assertSame([[1], [2]], ApiResponse::embeddings($response, 2));
    }

    public function test_pending_embedding_rejection_delivers_a_throwable(): void
    {
        $http = new Promise;
        $embedding = new EmbeddingPromise($http);
        $child = $embedding->then(null, static fn (Throwable $reason): Throwable => $reason);
        $http->reject('provider unavailable');

        $reason = $child->wait();

        $this->assertInstanceOf(RejectionException::class, $reason);
        $this->assertSame('provider unavailable', $reason->getReason());
    }

    public function test_pending_embedding_chain_follows_the_adopted_promise_state(): void
    {
        $firstHttp = new Promise;
        $secondHttp = new Promise;
        $first = new EmbeddingPromise($firstHttp);
        $second = new EmbeddingPromise($secondHttp);
        $child = $first->then(static fn (): EmbeddingPromise => $second);

        $firstHttp->resolve(new Response(200, [], '{"data":[{"embedding":[1]}]}'));
        Utils::queue()->run();

        $this->assertSame(HttpPromise::PENDING, $child->getState());

        $secondHttp->reject(new UnexpectedValueException('second request failed'));
        Utils::queue()->run();

        $this->assertSame(HttpPromise::REJECTED, $child->getState());
        $this->expectExceptionMessage('second request failed');
        $child->wait();
    }

    public function test_embedding_apis_accept_settled_http_promises(): void
    {
        $apis = [
            new CohereEmbeddingsApi('test', CohereInputType::SearchDocument),
            new InfinityClipApi,
            new InfinityEmbeddingsApi,
            new JinaClipApi,
            new OpenAIEmbeddingsApi('test'),
            new VoyageEmbeddingsApi('test'),
        ];
        $response = new Response(200, [], '{"data":[{"embedding":[1]}],"embeddings":{"float":[[1]]}}');
        $client = new Client(['handler' => static fn (): FulfilledPromise => new FulfilledPromise($response)]);

        foreach ($apis as $api) {
            (new ReflectionProperty($api, 'client'))->setValue($api, $client);

            $this->assertSame($response, $api->promiseEmbed('test', 1)->wait());
        }
    }

    public function test_clip_providers_send_canonical_base64_image_data(): void
    {
        $encoded = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aMSIAAAAASUVORK5CYII=';
        $wrapped = chunk_split($encoded, 64, "\r\n");

        foreach ([new InfinityClipApi, new JinaClipApi] as $api) {
            $request = null;
            $client = new Client(['handler' => static function (RequestInterface $sent) use (&$request): FulfilledPromise {
                $request = json_decode((string) $sent->getBody(), true, flags: JSON_THROW_ON_ERROR);

                return new FulfilledPromise(new Response(200, [], '{"data":[{"embedding":[1]}]}'));
            }]);
            (new ReflectionProperty($api, 'client'))->setValue($api, $client);

            foreach ([$wrapped, 'data:image/png;base64,'.$wrapped] as $input) {
                $api->embed($input, 1);

                $this->assertSame('data:image/png;base64,'.$encoded, $request['data'][0]['uri'] ?? $request['input']);
            }
        }
    }

    public function test_fake_embeddings_distinguish_zero_from_an_empty_input(): void
    {
        $api = new FakeEmbeddingsApi($this->createStub(EmbeddingsApi::class));
        $this->assertNotSame($api->embed('', 16), $api->embed('0', 16));
    }

    public function test_fake_reranker_matches_zero_as_a_token(): void
    {
        $api = new FakeRerankApi($this->createStub(RerankApi::class));
        $this->assertSame([1, 0], array_column($api->rerank(['empty', '0'], '0'), 'index'));
    }

    /** @test */
    public function infinity_sends_local_image_bytes_for_all_embedding_methods(): void
    {
        $api = new InfinityClipApi;
        $requests = [];
        $this->recordAdapterRequests($api, $requests);
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aMSIAAAAASUVORK5CYII=';
        $path = tempnam(sys_get_temp_dir(), 'sigmie-image-');

        try {
            file_put_contents($path, base64_decode($png));
            $api->embed($path, 512);
            $api->promiseEmbed($path, 512)->wait();
            $api->batchEmbed([['text' => $path]]);

            $uri = 'data:image/png;base64,'.$png;
            $this->assertSame([$uri, $uri, [$uri]], array_column($requests, 'input'));
            $this->assertSame(['image', 'image', 'image'], array_column($requests, 'modality'));
        } finally {
            unlink($path);
        }
    }

    protected function setUp(): void
    {
        if ($this->runsWithoutCluster()) {
            return;
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        if ($this->runsWithoutCluster()) {
            return;
        }

        parent::tearDown();
    }

    private function runsWithoutCluster(): bool
    {
        return in_array($this->getName(false), [
            'test_scalar_embedding_batch_items_use_the_default_dimensions',
            'test_api_response_rejects_a_non_numeric_rerank_score',
            'test_api_response_requires_a_json_object',
            'test_api_response_rejects_invalid_embedding_vectors',
            'test_classification_accepts_fixed_width_embeddings',
            'test_cohere_search_pool_requests_query_embeddings',
            'test_batch_dimensions_match_the_fake_request',
            'test_rerank_adapters_send_structured_document_text',
            'test_fake_rerank_scores_fit_the_provider_range',
            'test_embedding_response_preserves_input_order_when_iterated',
            'test_pending_embedding_rejection_delivers_a_throwable',
            'test_pending_embedding_chain_follows_the_adopted_promise_state',
            'test_embedding_apis_accept_settled_http_promises',
            'test_clip_providers_send_canonical_base64_image_data',
            'test_jina_chunks_at_its_advertised_batch_limit',
            'test_jina_returns_vectors_at_the_requested_width',
            'test_failed_embedding_decoding_rejects_the_embedding_promise',
            'test_pending_embedding_callbacks_return_http_promises',
            'test_string_cluster_error_returns_its_reason',
            'test_zero_rerank_results_skip_the_provider',
            'test_provider_batch_requests_respect_adapter_limits',
            'test_provider_large_openai_batches_default_to_native_width',
            'test_provider_cohere_v5_requests_native_width',
            'test_provider_cohere_omits_dimensions_outside_model_options',
            'test_provider_openai_rejects_out_of_range_dimensions',
            'test_provider_voyage_fixed_width_models_omit_dimension',
            'test_provider_voyage_omits_dimensions_outside_model_options',
            'test_provider_jina_uses_documented_http_gateway',
            'test_provider_jina_uploads_local_image_bytes',
            'test_provider_rerank_serializes_sparse_documents_as_a_list',
            'test_provider_rerank_skips_empty_document_requests',
            'test_provider_malformed_json_raises_decode_error',
            'test_provider_missing_rerank_results_are_rejected',
            'test_provider_partial_embedding_batches_are_rejected',
            'test_provider_duplicate_embedding_indices_are_rejected',
            'test_provider_rerank_rejects_indices_outside_the_input',
            'test_provider_failed_legacy_embed_cannot_resolve_on_second_wait',
            'test_provider_rate_limit_errors_preserve_the_provider_response',
            'test_provider_errors_keep_the_original_response',
            'test_provider_decodes_packed_float_embeddings',
            'test_openai_embedding_batches_respect_the_total_token_limit',
            'test_voyage_embedding_batches_respect_the_model_token_limit',
            'test_jina_reports_executor_failures_on_successful_http_responses',
            'test_provider_rerank_batches_preserve_global_document_indices',
            'test_voyage_rerank_batches_count_the_query_for_each_document',
            'self_hosted_adapters_preserve_the_base_url_path',
            'a_settled_callback_adopts_the_next_embedding_promise',
            'a_settled_callback_error_rejects_the_child_promise',
            'cohere_v4_omits_the_dimension_for_a_batch_without_a_width',
            'voyage_requests_the_native_output_width',
            'indexed_batch_vectors_follow_the_input_index',
            'test_legacy_embedding_requests_respect_provider_batch_limits',
            'infinity_splits_image_requests_at_32',
            'infinity_wraps_bare_image_bytes_in_a_data_uri',
            'infinity_sends_local_image_bytes_for_all_embedding_methods',
            'infinity_sends_dimensions_for_text_and_image',
            'cohere_v4_requests_output_dimension_on_single_and_promise',
            'ada_native_width_omits_dimensions',
            'cohere_embed_reads_the_embeddings_float_vector',
            'promise_embed_wait_keeps_the_vector_after_then',
            'a_fulfilled_embed_promise_runs_then',
            'batch_embed_keeps_each_text_on_its_key_and_dimension',
            'a_null_dimension_stays_apart_from_dimension_zero',
            'a_null_dimension_is_not_sent_as_zero',
            'an_elasticsearch_reason_is_the_tool_error',
            'a_tool_error_with_invalid_utf8_is_json',
            'test_fake_embeddings_distinguish_zero_from_an_empty_input',
            'test_fake_reranker_matches_zero_as_a_token',
        ], true);
    }

    /** @test */
    public function self_hosted_adapters_preserve_the_base_url_path(): void
    {
        $base = 'http://localhost/models/v1';
        foreach ([new InfinityEmbeddingsApi($base), new InfinityClipApi($base), new JinaClipApi($base), new InfinityRerankApi($base)] as $api) {
            $client = (new ReflectionProperty($api, 'client'))->getValue($api);
            $paths = [];
            $client->getConfig('handler')->setHandler(static function (RequestInterface $request) use (&$paths): Promise {
                $paths[] = $request->getUri()->getPath();
                $promise = new Promise;
                $promise->resolve(new Response(200, [], '{"data":[{"embedding":[1]}],"results":[]}'));

                return $promise;
            });
            if ($api instanceof InfinityRerankApi) {
                $api->rerank(['book'], 'book');
                $this->assertSame(['/models/v1/rerank'], $paths);

                continue;
            }

            $api->embed('book', 1);
            $api->promiseEmbed('book', 1)->wait();
            $api->batchEmbed([['text' => 'book']]);
            $path = $api instanceof JinaClipApi ? '/models/v1/post' : '/models/v1/embeddings';
            $this->assertSame(array_fill(0, 3, $path), $paths);
        }
    }

    /** @test */
    public function a_settled_callback_adopts_the_next_embedding_promise(): void
    {
        $provider = new OpenAIProvider('test');
        $requests = [];
        $this->recordAdapterRequests($provider, $requests);
        $first = $provider->promiseEmbed('first', 1);
        $child = $first->then(fn (): HttpPromise => $provider->promiseEmbed('second', 1));

        $this->assertSame(['_embeddings' => [1]], $child->wait());
    }

    /** @test */
    public function a_settled_callback_error_rejects_the_child_promise(): void
    {
        $provider = new OpenAIProvider('test');
        $requests = [];
        $this->recordAdapterRequests($provider, $requests);
        $promise = $provider->promiseEmbed('hello', 1);
        $error = new TypeError('Unexpected vector type');
        $child = $promise->then(static fn () => throw $error);
        $this->assertSame('rejected', $child->getState());
        $this->assertSame($error, $child->then(null, static fn ($reason) => $reason)->wait());
    }

    /** @test */
    public function cohere_v4_omits_the_dimension_for_a_batch_without_a_width(): void
    {
        $api = new CohereEmbeddingsApi('test', CohereInputType::SearchDocument, 'embed-v4.0');
        $bodies = [];
        (new ReflectionProperty($api, 'client'))->setValue($api, $this->recordingEmbeddingsClient($bodies));
        $result = $api->batchEmbed([['text' => 'book']]);
        $this->assertArrayNotHasKey('output_dimension', $bodies[0]);
        $this->assertSame([1, 0], $result[0]['vector']);
    }

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
            $this->assertCount(256, $api->embed('accounting audit', 256));
            $this->assertSame([], $api->batchEmbed([]));
            $this->assertCount(256, $api->batchEmbed([
                ['text' => 'accounting audit', 'dims' => 256],
            ])[0]['vector']);
            $this->assertCount(128, $api->batchEmbed([
                ['text' => 'accounting audit', 'dims' => 128],
            ])[0]['vector']);
            $this->assertSame(200, $api->promiseEmbed('accounting audit', 3)->wait()->getStatusCode());

            if ($api instanceof VoyageEmbeddingsApi) {
                $this->assertCount(3, $api->embedQuery('accounting audit', 3));
                $this->assertCount(256, $api->embedQuery('accounting audit', 256));
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

    /**
     * @test
     */
    public function cohere_embed_reads_the_embeddings_float_vector(): void
    {
        $api = new CohereEmbeddingsApi('test-key', CohereInputType::SearchDocument);
        $body = json_encode(['embeddings' => ['float' => [[0.25, 0.5, 0.75]]]]);
        $client = new Client([
            'handler' => HandlerStack::create(new MockHandler([
                new Response(200, [], $body),
            ])),
        ]);
        $property = (new ReflectionObject($api))->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($api, $client);

        $this->assertSame([0.25, 0.5, 0.75], $api->embed('hello', 3));
    }

    /**
     * @test
     */
    public function promise_embed_wait_keeps_the_vector_after_then(): void
    {
        foreach ([OpenAIProvider::class, VoyageProvider::class] as $class) {
            $waitOnly = $this->queuedEmbeddingProvider($class)->promiseEmbed('hello', 3)->wait();
            $this->assertSame(['_embeddings' => [0.1, 0.2, 0.3]], $waitOnly);

            $promise = $this->queuedEmbeddingProvider($class)->promiseEmbed('hello', 3);
            $promise->then(static fn (array $value): array => $value);

            $this->assertSame(['_embeddings' => [0.1, 0.2, 0.3]], $promise->wait());
            $this->assertNull($this->queuedEmbeddingProvider($class)->promiseEmbed('hello', 3)->wait(false));
        }
    }

    /**
     * @test
     */
    public function a_fulfilled_embed_promise_runs_then(): void
    {
        $body = json_encode(['data' => [['embedding' => [0.1, 0.2, 0.3]]]]);

        foreach ([OpenAIProvider::class, VoyageProvider::class] as $class) {
            $provider = new $class('test-key');
            $client = new Client([
                'handler' => static function () use ($body): Promise {
                    $promise = new Promise;
                    $promise->resolve(new Response(200, [], $body));

                    return $promise;
                },
            ]);
            $property = (new ReflectionObject($provider))->getProperty('client');
            $property->setAccessible(true);
            $property->setValue($provider, $client);

            $promise = $provider->promiseEmbed('hello', 3);
            $seen = null;
            $child = $promise->then(static function (array $value) use (&$seen): array {
                $seen = $value;

                return $value;
            });

            $this->assertSame(['_embeddings' => [0.1, 0.2, 0.3]], $seen);
            $this->assertSame('fulfilled', $child->getState());
            $this->assertSame(['_embeddings' => [0.1, 0.2, 0.3]], $promise->wait());
            $this->assertSame(['_embeddings' => [0.1, 0.2, 0.3]], $seen);
            $this->assertSame('fulfilled', $child->getState());
        }
    }

    private function queuedEmbeddingProvider(string $class): Embedder
    {
        $provider = new $class('test-key');
        $body = json_encode(['data' => [['embedding' => [0.1, 0.2, 0.3]]]]);
        $client = new Client([
            'handler' => HandlerStack::create(new MockHandler([
                new Response(200, [], $body),
            ])),
        ]);
        $property = (new ReflectionObject($provider))->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($provider, $client);

        return $provider;
    }

    private function realEmbeddingApis(): array
    {
        $openAI = new OpenAIEmbeddingsApi('test-key');
        $cohere = new CohereEmbeddingsApi('test-key', CohereInputType::SearchDocument);
        $infinity = new InfinityEmbeddingsApi('http://example.com');
        $voyage = new VoyageEmbeddingsApi('test-key');

        return [
            'openai' => $this->withMockEmbeddingClient($openAI, 'openai'),
            'cohere' => $this->withMockEmbeddingClient($cohere, 'cohere'),
            'infinity' => $this->withMockEmbeddingClient($infinity, 'infinity'),
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

    /**
     * @test
     */
    public function batch_embed_keeps_each_text_on_its_key_and_dimension(): void
    {
        $bodies = [];
        $api = new OpenAIEmbeddingsApi('test-key');
        $client = new Client(['handler' => HandlerStack::create(function (RequestInterface $request) use (&$bodies): PromiseInterface {
            $body = json_decode((string) $request->getBody(), true);
            $bodies[] = $body;
            $dims = (int) $body['dimensions'];
            $data = [];

            foreach (array_keys(array_values($body['input'])) as $index) {
                $data[] = [
                    'embedding' => array_fill(0, $dims, $index === 0 ? 1 : 2),
                    'index' => $index,
                ];
            }

            return Create::promiseFor(new Response(200, [], json_encode(['data' => $data])));
        })]);
        (new ReflectionProperty($api, 'client'))->setValue($api, $client);

        $sparse = $api->batchEmbed([
            1 => ['text' => 'alpha', 'dims' => 3],
            2 => ['text' => 'beta', 'dims' => 3],
        ]);

        $this->assertSame(['alpha', 'beta'], $bodies[0]['input']);
        $this->assertSame(3, $bodies[0]['dimensions']);
        $this->assertSame([1, 1, 1], $sparse[1]['vector']);
        $this->assertSame([2, 2, 2], $sparse[2]['vector']);
        $this->assertArrayNotHasKey(0, $sparse);

        $mixed = $api->batchEmbed([
            ['text' => 'short', 'dims' => 3],
            ['text' => 'long', 'dims' => 5],
        ]);

        $this->assertCount(3, $mixed[0]['vector']);
        $this->assertCount(5, $mixed[1]['vector']);

        $voyageBodies = [];
        $voyage = new VoyageEmbeddingsApi('test-key');
        $voyageClient = new Client(['handler' => HandlerStack::create(function (RequestInterface $request) use (&$voyageBodies): PromiseInterface {
            $body = json_decode((string) $request->getBody(), true);
            $voyageBodies[] = $body;

            return Create::promiseFor(new Response(200, [], json_encode([
                'data' => [
                    ['embedding' => [1, 0, 0]],
                    ['embedding' => [0, 1, 0]],
                ],
            ])));
        })]);
        (new ReflectionProperty($voyage, 'client'))->setValue($voyage, $voyageClient);

        $voyageResult = $voyage->batchEmbed([
            1 => ['text' => 'alpha', 'dims' => 3],
            2 => ['text' => 'beta', 'dims' => 3],
        ]);

        $this->assertSame(['alpha', 'beta'], $voyageBodies[0]['input']);
        $this->assertSame([1, 0, 0], $voyageResult[1]['vector']);
        $this->assertSame([0, 1, 0], $voyageResult[2]['vector']);
        $this->assertArrayNotHasKey(0, $voyageResult);
    }

    /**
     * @test
     */
    public function a_null_dimension_stays_apart_from_dimension_zero(): void
    {
        $this->assertSame([
            ['dims' => null, 'keys' => [0], 'texts' => ['unset']],
            ['dims' => 0, 'keys' => [1], 'texts' => ['zero']],
        ], batch_slots([
            ['text' => 'unset', 'dims' => null],
            ['text' => 'zero', 'dims' => 0],
        ], null));
    }

    /**
     * @test
     */
    public function a_null_dimension_is_not_sent_as_zero(): void
    {
        $payload = [
            ['text' => 'plain'],
            ['text' => 'unset', 'dims' => null],
            ['text' => 'custom', 'dims' => 256],
        ];

        $openai = new OpenAIEmbeddingsApi('test-key');
        $openaiBodies = [];
        (new ReflectionProperty($openai, 'client'))->setValue($openai, $this->recordingEmbeddingsClient($openaiBodies));
        $openai->batchEmbed($payload);

        $provider = new OpenAIProvider('test-key');
        $providerBodies = [];
        (new ReflectionProperty($provider, 'client'))->setValue($provider, $this->recordingEmbeddingsClient($providerBodies));
        $provider->batchEmbed($payload);

        $cohere = new CohereEmbeddingsApi('test-key', CohereInputType::SearchDocument, 'embed-v4.0');
        $cohereBodies = [];
        (new ReflectionProperty($cohere, 'client'))->setValue($cohere, $this->recordingEmbeddingsClient($cohereBodies));
        $cohere->batchEmbed([
            ['text' => 'unset', 'dims' => null],
            ['text' => 'custom', 'dims' => 256],
        ]);

        $this->assertSame(1536, $openaiBodies[0]['dimensions']);
        $this->assertArrayNotHasKey('dimensions', $openaiBodies[1]);
        $this->assertSame(256, $openaiBodies[2]['dimensions']);
        $this->assertSame(1536, $providerBodies[0]['dimensions']);
        $this->assertArrayNotHasKey('dimensions', $providerBodies[1]);
        $this->assertSame(256, $providerBodies[2]['dimensions']);
        $this->assertArrayNotHasKey('output_dimension', $cohereBodies[0]);
        $this->assertSame(256, $cohereBodies[1]['output_dimension']);
    }

    /**
     * @test
     */
    public function an_elasticsearch_reason_is_the_tool_error(): void
    {
        $tool = new class
        {
            use HandlesToolErrors;

            public function run(Throwable $e): string
            {
                return $this->guard(static function () use ($e): string {
                    throw $e;
                });
            }
        };

        $expected = '{"error":"0 Check the field names and the filter/sort syntax in this tool\'s description, then try again."}';
        $raw = new Exception(json_encode([
            'error' => [
                'root_cause' => [
                    ['type' => 'parsing_exception', 'reason' => '0'],
                ],
                'reason' => '0',
            ],
        ]));
        $wrapped = new ElasticsearchException([
            'type' => 'parsing_exception',
            'code' => 400,
            'json' => [
                'error' => [
                    'root_cause' => [
                        ['type' => 'parsing_exception', 'reason' => '0'],
                    ],
                    'reason' => '0',
                ],
            ],
            'body' => '',
            'request' => ['query' => '0'],
        ], 400);

        $this->assertSame($expected, $tool->run($raw));
        $this->assertSame($expected, $tool->run($wrapped));
    }

    /**
     * @test
     */
    public function a_tool_error_with_invalid_utf8_is_json(): void
    {
        $tool = new class
        {
            use HandlesToolErrors;

            public function run(Throwable $e): string
            {
                return $this->guard(static function () use ($e): string {
                    throw $e;
                });
            }
        };

        $json = $tool->run(new Exception("bad \xB1 bytes"));

        $this->assertIsArray(json_decode($json, true));
        $this->assertStringStartsWith('{"error":', $json);
    }

    /** @test */
    public function voyage_requests_the_native_output_width(): void
    {
        $api = new VoyageEmbeddingsApi('test', 'voyage-3-large');
        $requests = [];
        $this->recordAdapterRequests($api, $requests);
        $vectors = [
            $api->embed('book', 2048),
            $api->embedQuery('book', 2048),
            $api->batchEmbed([['text' => 'book', 'dims' => 2048]])[0]['vector'],
        ];
        $api->promiseEmbed('book', 2048)->wait();

        $this->assertSame(array_fill(0, 4, 2048), array_column($requests, 'output_dimension'));
        $this->assertSame(['document', 'query', 'document', 'document'], array_column($requests, 'input_type'));
        foreach ($vectors as $vector) {
            $this->assertSame(1, $vector[2047]);
        }

        $legacy = new VoyageEmbeddingsApi('test', 'voyage-2');
        $requests = [];
        $this->recordAdapterRequests($legacy, $requests);
        $this->assertCount(3, $legacy->embed('book', 3));
        $this->assertArrayNotHasKey('output_dimension', $requests[0]);
    }

    public static function indexedAdapters(): array
    {
        return [
            'Voyage provider' => [new VoyageProvider('test')],
            'Voyage API' => [new VoyageEmbeddingsApi('test')],
            'Infinity text' => [new InfinityEmbeddingsApi],
            'Infinity CLIP text' => [new InfinityClipApi],
            'Infinity CLIP images' => [new InfinityClipApi, true],
        ];
    }

    /** @dataProvider legacyEmbeddingBatchSizes */
    public function test_legacy_embedding_requests_respect_provider_batch_limits(string $provider, int $limit): void
    {
        $api = new $provider('test');
        $requests = [];
        $this->recordAdapterRequests($api, $requests);

        foreach ([0, $limit, $limit + 1, $limit * 2] as $size) {
            $requests = [];
            $payload = [];
            for ($i = 0; $i < $size; $i++) {
                $payload['doc-'.$i] = ['text' => 'document '.$i];
            }

            $result = $api->batchEmbed($payload);
            $this->assertSame(array_chunk(array_column($payload, 'text'), $limit), array_column($requests, 'input'));
            $this->assertSame(array_keys($payload), array_keys($result));
            $this->assertSame(
                array_map(static fn (int $index): array => [$index % $limit + 1], array_keys(array_values($payload))),
                array_column($result, 'vector'),
            );
        }
    }

    public static function legacyEmbeddingBatchSizes(): array
    {
        return [
            'OpenAI' => [OpenAIProvider::class, 2048],
            'Voyage' => [VoyageProvider::class, 1000],
        ];
    }

    /**
     * @test
     *
     * @dataProvider indexedAdapters
     */
    public function indexed_batch_vectors_follow_the_input_index(Embedder|EmbeddingsApi $api, bool $images = false): void
    {
        $requests = [];
        $this->recordAdapterRequests($api, $requests);
        $inputs = $images
            ? ['https://example.com/a.png', 'https://example.com/b.png']
            : ['first', 'second'];
        $result = $api->batchEmbed(array_map(fn (string $input): array => ['text' => $input], $inputs));
        $this->assertSame([1, 2], array_column(array_column($result, 'vector'), 0));
    }

    /** @test */
    public function infinity_splits_image_requests_at_32(): void
    {
        $api = new InfinityClipApi;
        $requests = [];
        $this->recordAdapterRequests($api, $requests);
        $payload = [];
        foreach (range(1, 33) as $index) {
            $payload[] = ['text' => sprintf('https://example.com/%s.png', $index)];
        }

        $result = $api->batchEmbed($payload);
        $this->assertSame([32, 1], array_map('count', array_column($requests, 'input')));
        $this->assertSame([32], $result[31]['vector']);
        $this->assertSame([1], $result[32]['vector']);
    }

    /** @test */
    public function infinity_wraps_bare_image_bytes_in_a_data_uri(): void
    {
        $api = new InfinityClipApi;
        $requests = [];
        $this->recordAdapterRequests($api, $requests);
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aMSIAAAAASUVORK5CYII=';
        $uri = 'data:image/png;base64,'.$png;
        $api->embed($png, 512);
        $api->promiseEmbed($png, 512)->wait();
        $api->batchEmbed([['text' => $png], ['text' => $uri]]);
        $this->assertSame([$uri, $uri, [$uri, $uri]], array_column($requests, 'input'));
        $this->assertSame(['image', 'image', 'image'], array_column($requests, 'modality'));
    }

    /** @test */
    public function infinity_sends_dimensions_for_text_and_image(): void
    {
        foreach ([new InfinityEmbeddingsApi, new InfinityClipApi] as $api) {
            foreach ($api instanceof InfinityClipApi ? ['book', 'https://example.com/a.png'] : ['book'] as $input) {
                $requests = [];
                $this->recordAdapterRequests($api, $requests);
                $api->embed($input, 256);
                $api->promiseEmbed($input, 256)->wait();
                $api->batchEmbed([['text' => $input, 'dims' => 128], ['text' => $input, 'dims' => null]]);
                $this->assertSame([256, 256, 128], array_column($requests, 'dimensions'));
                $this->assertArrayNotHasKey('dimensions', $requests[3]);
            }
        }
    }

    /** @test */
    public function cohere_v4_requests_output_dimension_on_single_and_promise(): void
    {
        foreach (['embed-v4.0', 'embed-english-v3.0'] as $model) {
            $api = new CohereEmbeddingsApi('test', CohereInputType::SearchDocument, $model);
            $requests = [];
            $this->recordAdapterRequests($api, $requests);
            $api->promiseEmbed('book', 256)->wait();
            $this->assertSame($model === 'embed-v4.0' ? [256] : [], array_column($requests, 'output_dimension'));
            $api->embed('book', 256);
            $this->assertSame($model === 'embed-v4.0' ? [256, 256] : [], array_column($requests, 'output_dimension'));
            if ($model === 'embed-v4.0') {
                $this->assertSame(['/v2/embed', '/v2/embed'], array_column($requests, 'path'));
            }
        }
    }

    public static function openAIAdapters(): array
    {
        return [
            'provider' => [OpenAIProvider::class],
            'API' => [OpenAIEmbeddingsApi::class],
        ];
    }

    /**
     * @test
     *
     * @dataProvider openAIAdapters
     */
    public function ada_native_width_omits_dimensions(string $class): void
    {
        $api = new $class('test', 'text-embedding-ada-002');
        $requests = [];
        $this->recordAdapterRequests($api, $requests);
        $api->embed('book', 1536);
        $api->promiseEmbed('book', 1536)->wait();
        $api->batchEmbed([['text' => 'book', 'dims' => 1536]]);
        $this->assertSame([], array_column($requests, 'dimensions'));

        $this->expectException(InvalidArgumentException::class);
        $api->embed('book', 512);
    }

    private function recordAdapterRequests(Embedder|EmbeddingsApi $api, array &$requests): void
    {
        $client = new Client(['handler' => static function (RequestInterface $request) use (&$requests): Promise {
            $body = json_decode((string) $request->getBody(), true);
            $requests[] = ['path' => $request->getUri()->getPath(), ...$body];
            $data = [];
            $width = $body['output_dimension'] ?? ($body['model'] === 'voyage-3-large' ? 1024 : 1);
            foreach (array_keys((array) ($body['input'] ?? $body['texts'])) as $index) {
                $data[] = ['index' => $index, 'embedding' => array_fill(0, $width, $index + 1)];
            }

            $promise = new Promise;
            $promise->resolve(new Response(200, [], json_encode([
                'data' => array_reverse($data),
                'embeddings' => ['float' => array_column($data, 'embedding')],
            ])));

            return $promise;
        }]);
        (new ReflectionProperty($api, 'client'))->setValue($api, $client);
    }

    private function recordingEmbeddingsClient(array &$bodies): Client
    {
        return new Client(['handler' => function ($request) use (&$bodies): PromiseInterface {
            $body = json_decode((string) $request->getBody(), true);
            $bodies[] = $body;
            $vectors = array_fill(0, count((array) ($body['input'] ?? $body['texts'])), [1, 0]);

            return Create::promiseFor(new Response(200, [], json_encode([
                'data' => array_map(static fn (array $vector): array => ['embedding' => $vector], $vectors),
                'embeddings' => ['float' => $vectors],
            ])));
        }]);
    }

    public static function adapterBatchLimits(): array
    {
        return [
            'OpenAI' => [new OpenAIEmbeddingsApi('test'), 2048, 'input'],
            'Cohere' => [new CohereEmbeddingsApi('test', CohereInputType::SearchDocument), 96, 'texts'],
            'Voyage' => [new VoyageEmbeddingsApi('test'), 1000, 'input'],
            'Infinity' => [new InfinityEmbeddingsApi, 2048, 'input'],
        ];
    }

    /** @dataProvider adapterBatchLimits */
    public function test_provider_batch_requests_respect_adapter_limits(EmbeddingsApi $api, int $limit, string $inputField): void
    {
        $requests = [];
        $this->recordAdapterRequests($api, $requests);
        $payload = array_fill(0, $limit + 1, ['text' => 'book']);
        $result = $api->batchEmbed($payload);

        $this->assertSame([$limit, 1], array_map('count', array_column($requests, $inputField)));
        $this->assertSame([$limit], $result[$limit - 1]['vector']);
        $this->assertSame([1], $result[$limit]['vector']);
    }

    /** @dataProvider openAIAdapters */
    public function test_provider_large_openai_batches_default_to_native_width(string $class): void
    {
        $api = new $class('test', 'text-embedding-3-large');
        $requests = [];
        $this->recordAdapterRequests($api, $requests);
        $api->batchEmbed([['text' => 'book']]);

        $this->assertSame(3072, $requests[0]['dimensions']);
    }

    public function test_provider_cohere_v5_requests_native_width(): void
    {
        $api = new CohereEmbeddingsApi('test', CohereInputType::SearchDocument, 'embed-v5.0-fast');
        $requests = [];
        $this->recordAdapterRequests($api, $requests);
        $api->embed('book', 768);
        $api->promiseEmbed('book', 768)->wait();
        $api->batchEmbed([['text' => 'book', 'dims' => 768]]);

        $this->assertSame([768, 768, 768], array_column($requests, 'output_dimension'));
        $this->assertSame(array_fill(0, 3, '/v2/embed'), array_column($requests, 'path'));
    }

    public function test_provider_cohere_omits_dimensions_outside_model_options(): void
    {
        $api = new CohereEmbeddingsApi('test', CohereInputType::SearchDocument, 'embed-v4.0');
        $requests = [];
        $this->recordAdapterRequests($api, $requests);
        $this->assertCount(384, $api->embed('book', 384));
        $api->promiseEmbed('book', 0)->wait();
        $this->assertCount(384, $api->batchEmbed([['text' => 'book', 'dims' => 384]])[0]['vector']);

        $this->assertSame([], array_column($requests, 'output_dimension'));
    }

    public static function invalidOpenAIDimensions(): array
    {
        return [
            'API zero' => [OpenAIEmbeddingsApi::class, 0],
            'API wider than small' => [OpenAIEmbeddingsApi::class, 2048],
            'provider zero' => [OpenAIProvider::class, 0],
            'provider wider than small' => [OpenAIProvider::class, 2048],
        ];
    }

    /** @dataProvider invalidOpenAIDimensions */
    public function test_provider_openai_rejects_out_of_range_dimensions(string $class, int $dimensions): void
    {
        $api = new $class('test');
        $requests = [];
        $this->recordAdapterRequests($api, $requests);

        $this->expectException(InvalidArgumentException::class);
        $api->batchEmbed([['text' => 'book', 'dims' => $dimensions]]);
    }

    public function test_provider_voyage_fixed_width_models_omit_dimension(): void
    {
        $api = new VoyageProvider('test', 'voyage-3');
        $requests = [];
        $this->recordAdapterRequests($api, $requests);
        $vectors = [
            $api->embed('book', 3),
            $api->promiseEmbed('book', 3)->wait()['_embeddings'],
            $api->batchEmbed([['text' => 'book', 'dims' => 3]])[0]['vector'],
        ];

        $this->assertSame([], array_column($requests, 'output_dimension'));
        $this->assertSame(array_fill(0, 3, [1, 0.0, 0.0]), $vectors);
    }

    public function test_provider_voyage_omits_dimensions_outside_model_options(): void
    {
        $api = new VoyageEmbeddingsApi('test', 'voyage-4');
        $requests = [];
        $this->recordAdapterRequests($api, $requests);
        $this->assertCount(384, $api->embed('book', 384));
        $this->assertCount(384, $api->embedQuery('book', 384));
        $this->assertCount(384, $api->batchEmbed([['text' => 'book', 'dims' => 384]])[0]['vector']);
        $api->promiseEmbed('book', 384)->wait();

        $this->assertSame([], array_column($requests, 'output_dimension'));
    }

    public function test_string_cluster_error_returns_its_reason(): void
    {
        $tool = new class
        {
            use HandlesToolErrors { toolErrorMessage as public; }
        };
        $error = new ElasticsearchException(['json' => ['error' => 'Index is closed'], 'request' => ['size' => 10]], 400);

        $this->assertSame("Index is closed Check the field names and the filter/sort syntax in this tool's description, then try again.", $tool->toolErrorMessage($error));
    }

    /** @dataProvider rerankAdapters */
    public function test_zero_rerank_results_skip_the_provider(RerankApi $api): void
    {
        $requests = [];
        $this->mockAdapterResponses($api, $requests, []);

        $this->assertSame([], $api->rerank(['book'], 'book', 0));
        $this->assertSame([], $requests);
    }

    public function test_jina_chunks_at_its_advertised_batch_limit(): void
    {
        $api = new JinaClipApi;
        $limit = $api->maxBatchSize();
        $requests = [];
        $page = array_fill(0, $limit, ['embedding' => [1]]);
        $this->mockAdapterResponses($api, $requests, [
            new Response(200, [], json_encode(['data' => $page])),
            new Response(200, [], '{"data":[{"embedding":[2]}]}'),
        ]);

        $result = $api->batchEmbed(array_fill(0, $limit + 1, ['text' => 'book']));
        $this->assertSame([$limit, 1], array_map('count', array_column($requests, 'data')));
        $this->assertSame([2], $result[$limit]['vector']);
    }

    public function test_jina_returns_vectors_at_the_requested_width(): void
    {
        $api = new JinaClipApi;
        $requests = [];
        $this->mockAdapterResponses($api, $requests, [
            new Response(200, [], '{"data":[{"embedding":[1,2,3]}]}'),
            new Response(200, [], '{"data":[{"embedding":[1,2,3]}]}'),
        ]);

        $this->assertSame([1, 2], $api->embed('book', 2));
        $this->assertSame([1, 2], $api->batchEmbed([['text' => 'book', 'dims' => 2]])[0]['vector']);
    }

    public function test_failed_embedding_decoding_rejects_the_embedding_promise(): void
    {
        $api = new OpenAIProvider('test');
        $requests = [];
        $this->mockAdapterResponses($api, $requests, [new Response(200, [], '{"data":[]}')]);
        $promise = $api->promiseEmbed('book', 1);
        $child = $promise->then(null, static fn (UnexpectedValueException $reason): string => 'recovered');

        $promise->wait(false);
        $this->assertSame('rejected', $promise->getState());
        $this->assertSame('recovered', $child->wait());
    }

    public function test_pending_embedding_callbacks_return_http_promises(): void
    {
        $api = $this->queuedEmbeddingProvider(OpenAIProvider::class);
        $child = $api->promiseEmbed('book', 3)->then(static fn (array $row): array => $row['_embeddings']);

        $this->assertInstanceOf(HttpPromise::class, $child);
        $this->assertSame([0.1, 0.2, 0.3], $child->wait());
    }

    public function test_provider_jina_uses_documented_http_gateway(): void
    {
        $api = new JinaClipApi;
        $requests = [];
        $this->mockAdapterResponses($api, $requests, array_map(static fn (): Response => new Response(200, [], '{"data":[{"embedding":[1]}]}'), range(1, 3)));
        $api->embed('book', 512);
        $api->promiseEmbed('book', 512)->wait();
        $api->batchEmbed([['text' => 'book']]);

        $this->assertSame(array_fill(0, 3, '/post'), array_column($requests, 'path'));
        $this->assertSame(array_fill(0, 3, '/'), array_column($requests, 'execEndpoint'));
    }

    public function test_provider_jina_uploads_local_image_bytes(): void
    {
        $api = new JinaClipApi;
        $requests = [];
        $this->mockAdapterResponses($api, $requests, array_map(static fn (): Response => new Response(200, [], '{"data":[{"embedding":[1]}]}'), range(1, 3)));
        $gif = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
        $path = tempnam(sys_get_temp_dir(), 'sigmie-jina-');
        try {
            file_put_contents($path, base64_decode($gif));
            $api->embed($path, 512);
            $api->promiseEmbed($path, 512)->wait();
            $api->batchEmbed([['text' => $path]]);
        } finally {
            unlink($path);
        }

        $this->assertSame(array_fill(0, 3, [['blob' => $gif]]), array_column($requests, 'data'));
    }

    public static function rerankAdapters(): array
    {
        return [
            'Cohere' => [new CohereRerankApi('test'), 'results', 'top_n'],
            'Voyage' => [new VoyageRerankApi('test'), 'data', 'top_k'],
            'Infinity' => [new InfinityRerankApi, 'results', 'top_n'],
        ];
    }

    /** @dataProvider rerankAdapters */
    public function test_provider_rerank_serializes_sparse_documents_as_a_list(RerankApi $api, string $field, string $limit): void
    {
        $requests = [];
        $this->mockAdapterResponses($api, $requests, [
            new Response(200, [], json_encode([$field => [['index' => 1, 'relevance_score' => 0.9]]])),
        ]);

        $this->assertSame([['index' => 1, 'score' => 0.9]], $api->rerank([2 => 'first', 8 => 'second'], 'book', 1));
        $this->assertSame(['first', 'second'], $requests[0]['documents']);
        $this->assertSame(1, $requests[0][$limit]);
    }

    /** @dataProvider rerankAdapters */
    public function test_provider_rerank_skips_empty_document_requests(RerankApi $api, string $field): void
    {
        $requests = [];
        $this->mockAdapterResponses($api, $requests, [new Response(200, [], json_encode([$field => []]))]);

        $this->assertSame([], $api->rerank([], 'book'));
        $this->assertSame([], $requests);
    }

    public function test_provider_malformed_json_raises_decode_error(): void
    {
        $api = new InfinityRerankApi;
        $requests = [];
        $this->mockAdapterResponses($api, $requests, [new Response(200, [], '{"results":')]);

        $this->expectException(JsonException::class);
        $api->rerank(['book'], 'book');
    }

    public function test_provider_missing_rerank_results_are_rejected(): void
    {
        $api = new InfinityRerankApi;
        $requests = [];
        $this->mockAdapterResponses($api, $requests, [
            new Response(200, [], '{"results":[]}'),
            new Response(200, [], '{"detail":"Reranker unavailable"}'),
        ]);

        $this->assertSame([], $api->rerank(['book'], 'book'));
        $this->expectException(UnexpectedValueException::class);
        $api->rerank(['book'], 'book');
    }

    public function test_provider_partial_embedding_batches_are_rejected(): void
    {
        $api = new OpenAIEmbeddingsApi('test');
        $requests = [];
        $this->mockAdapterResponses($api, $requests, [
            new Response(200, [], '{"data":[{"index":0,"embedding":[1]}]}'),
        ]);

        $this->expectException(UnexpectedValueException::class);
        $api->batchEmbed(['a' => ['text' => 'first'], 'b' => ['text' => 'second']]);
    }

    public function test_provider_duplicate_embedding_indices_are_rejected(): void
    {
        $api = new InfinityClipApi;
        $requests = [];
        $this->mockAdapterResponses($api, $requests, [
            new Response(200, [], '{"data":[{"index":0,"embedding":[1]},{"index":0,"embedding":[2]}]}'),
        ]);

        $this->expectException(UnexpectedValueException::class);
        $api->batchEmbed([['text' => 'first'], ['text' => 'second']]);
    }

    public function test_provider_rerank_rejects_indices_outside_the_input(): void
    {
        $api = new VoyageRerankApi('test');
        $requests = [];
        $this->mockAdapterResponses($api, $requests, [
            new Response(200, [], '{"data":[{"index":2,"relevance_score":0.9}]}'),
        ]);

        $this->expectException(UnexpectedValueException::class);
        $api->rerank(['first', 'second'], 'book');
    }

    public function test_provider_failed_legacy_embed_cannot_resolve_on_second_wait(): void
    {
        $api = new OpenAIProvider('test');
        $requests = [];
        $this->mockAdapterResponses($api, $requests, [new Response(200, [], '{"data":[]}')]);
        $promise = $api->promiseEmbed('book', 1);

        $firstError = null;
        try {
            $promise->wait();
        } catch (Throwable $throwable) {
            $firstError = $throwable;
        }

        $this->assertNotNull($firstError);

        $this->expectException(UnexpectedValueException::class);
        $promise->wait();
    }

    public function test_provider_rate_limit_errors_preserve_the_provider_response(): void
    {
        $api = new OpenAIEmbeddingsApi('test');
        $requests = [];
        $body = '{"error":{"message":"Rate limit exceeded","code":"rate_limit_exceeded"}}';
        $this->mockAdapterResponses($api, $requests, [
            new Response(429, ['Retry-After' => '2'], $body),
            new Response(429, ['Retry-After' => '2'], $body),
        ]);

        foreach ([fn (): array => $api->embed('book', 1), fn () => $api->promiseEmbed('book', 1)->wait()] as $embed) {
            try {
                $embed();
                $this->fail('A rate-limited request resolved successfully.');
            } catch (ClientException $error) {
                $this->assertSame(429, $error->getResponse()->getStatusCode());
                $this->assertSame('2', $error->getResponse()->getHeaderLine('Retry-After'));
                $this->assertSame($body, (string) $error->getResponse()->getBody());
            }
        }
    }

    public static function providerAdapters(): array
    {
        return [
            'OpenAI embeddings' => [new OpenAIEmbeddingsApi('test')],
            'Voyage embeddings' => [new VoyageEmbeddingsApi('test')],
            'Cohere embeddings' => [new CohereEmbeddingsApi('test', CohereInputType::SearchDocument)],
            'Infinity embeddings' => [new InfinityEmbeddingsApi],
            'Infinity CLIP' => [new InfinityClipApi],
            'Jina CLIP' => [new JinaClipApi],
            'Voyage rerank' => [new VoyageRerankApi('test')],
            'Cohere rerank' => [new CohereRerankApi('test')],
            'Infinity rerank' => [new InfinityRerankApi],
        ];
    }

    /** @dataProvider providerAdapters */
    public function test_provider_errors_keep_the_original_response(EmbeddingsApi|RerankApi $api): void
    {
        $requests = [];
        $this->mockAdapterResponses($api, $requests, [
            new Response(429, ['Retry-After' => '2'], '{"message":"rate limit exceeded"}'),
            new Response(503, ['Content-Type' => 'text/plain'], 'Service unavailable'),
        ]);
        $request = $api instanceof EmbeddingsApi
            ? fn (): array => $api->batchEmbed([['text' => 'book', 'dims' => 1]])
            : fn (): array => $api->rerank(['book'], 'book');

        $this->assertSame([], $api instanceof EmbeddingsApi ? $api->batchEmbed([]) : $api->rerank([], 'book'));
        $this->assertSame([], $requests);

        try {
            $request();
            $this->fail('The rate-limited request returned data.');
        } catch (ClientException $clientException) {
            $this->assertSame(429, $clientException->getResponse()->getStatusCode());
            $this->assertSame('2', $clientException->getResponse()->getHeaderLine('Retry-After'));
            $this->assertSame('{"message":"rate limit exceeded"}', (string) $clientException->getResponse()->getBody());
        }

        try {
            $request();
            $this->fail('The unavailable service returned data.');
        } catch (ServerException $serverException) {
            $this->assertSame(503, $serverException->getResponse()->getStatusCode());
            $this->assertSame('Service unavailable', (string) $serverException->getResponse()->getBody());
        }
    }

    public static function packedEmbeddingAdapters(): array
    {
        return [
            'OpenAI' => [new OpenAIEmbeddingsApi('test')],
            'Voyage' => [new VoyageEmbeddingsApi('test')],
            'Infinity' => [new InfinityEmbeddingsApi],
            'Infinity CLIP' => [new InfinityClipApi],
            'Legacy OpenAI' => [new OpenAIProvider('test')],
            'Legacy Voyage' => [new VoyageProvider('test')],
        ];
    }

    /** @dataProvider packedEmbeddingAdapters */
    public function test_provider_decodes_packed_float_embeddings(Embedder|EmbeddingsApi $api): void
    {
        $requests = [];
        $this->mockAdapterResponses($api, $requests, [new Response(200, [], json_encode([
            'data' => [
                ['index' => 1, 'embedding' => base64_encode(pack('g*', -0.5, 0.125))],
                ['index' => 0, 'embedding' => base64_encode(pack('g*', 0.25, 0.75))],
            ],
            'usage' => ['prompt_tokens' => 2, 'total_tokens' => 2],
        ], JSON_THROW_ON_ERROR))]);

        $batch = $api->batchEmbed([
            'first' => ['text' => 'book', 'dims' => 2],
            'second' => ['text' => 'guide', 'dims' => 2],
        ]);

        $this->assertSame([0.25, 0.75], $batch['first']['vector']);
        $this->assertSame([-0.5, 0.125], $batch['second']['vector']);
    }

    /** @dataProvider openAIAdapters */
    public function test_openai_embedding_batches_respect_the_total_token_limit(string $class): void
    {
        $api = new $class('test');
        $input = array_fill(0, 37, ['text' => str_repeat(' x', 8192), 'dims' => 1]);
        $client = (new ReflectionProperty($api, 'client'))->getValue($api);
        $client->getConfig('handler')->setHandler(static function (RequestInterface $request): PromiseInterface {
            $texts = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR)['input'];
            // OpenAI's cl100k_base encodes each space-prefixed x as one token.
            if (count($texts) * 8192 > 300_000) {
                return new FulfilledPromise(new Response(400, [], '{"error":{"message":"Request exceeds 300000 tokens"}}'));
            }

            return new FulfilledPromise(new Response(200, [], json_encode([
                'data' => array_fill(0, count($texts), ['embedding' => [1]]),
            ], JSON_THROW_ON_ERROR)));
        });

        $result = $api->batchEmbed($input);

        $this->assertSame(array_fill(0, 37, [1]), array_column($result, 'vector'));
    }

    public function test_voyage_embedding_batches_respect_the_model_token_limit(): void
    {
        foreach ([new VoyageEmbeddingsApi('test', 'voyage-4-large'), new VoyageProvider('test', 'voyage-4-large')] as $api) {
            $client = (new ReflectionProperty($api, 'client'))->getValue($api);
            $client->getConfig('handler')->setHandler(static function (RequestInterface $request): PromiseInterface {
                $texts = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR)['input'];
                // The voyage-4-large tokenizer encodes each space-prefixed x as one token.
                if (count($texts) * 8000 > 120_000) {
                    return new FulfilledPromise(new Response(400, [], '{"detail":"Total number of tokens in the batch exceeds the limit"}'));
                }

                return new FulfilledPromise(new Response(200, [], json_encode([
                    'data' => array_fill(0, count($texts), ['embedding' => [1]]),
                ], JSON_THROW_ON_ERROR)));
            });

            $result = $api->batchEmbed(array_fill(0, 16, ['text' => str_repeat(' x', 8000), 'dims' => 1]));

            $this->assertSame(array_fill(0, 16, [1]), array_column($result, 'vector'));
        }
    }

    public function test_jina_reports_executor_failures_on_successful_http_responses(): void
    {
        $api = new JinaClipApi;
        $requests = [];
        $response = new Response(200, [], '{"header":{"status":{"code":"ERROR","description":"Invalid image"}},"data":[{"uri":"https://example.com/book.png"}]}');
        $this->mockAdapterResponses($api, $requests, [$response, $response, $response]);
        $image = 'https://example.com/book.png';

        foreach ([
            fn (): array => $api->embed($image, 512),
            fn (): array => $api->batchEmbed([['text' => $image, 'dims' => 512]]),
            fn () => $api->promiseEmbed($image, 512)->wait(),
        ] as $embed) {
            try {
                $embed();
                $this->fail('The failed executor returned an embedding.');
            } catch (UnexpectedValueException $error) {
                $this->assertStringContainsString('Invalid image', $error->getMessage());
            }
        }
    }

    public static function rerankAdapterLimits(): array
    {
        return [
            'Voyage' => [new VoyageRerankApi('test'), 'data', 1000],
            'Infinity' => [new InfinityRerankApi, 'results', 2048],
        ];
    }

    /** @dataProvider rerankAdapterLimits */
    public function test_provider_rerank_batches_preserve_global_document_indices(RerankApi $api, string $field, int $limit): void
    {
        $requests = [];
        $this->mockAdapterResponses($api, $requests, [
            new Response(200, [], json_encode([$field => [
                ['index' => 17, 'relevance_score' => 0.75],
                ['index' => 3, 'relevance_score' => 0.5],
            ]], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode([$field => [
                ['index' => 0, 'relevance_score' => 0.875],
            ]], JSON_THROW_ON_ERROR)),
        ]);

        $results = $api->rerank(array_fill(0, $limit + 1, 'book'), 'book', 2);

        $this->assertSame([$limit, 1], array_map('count', array_column($requests, 'documents')));
        $this->assertSame([
            ['index' => $limit, 'score' => 0.875],
            ['index' => 17, 'score' => 0.75],
        ], $results);
    }

    public function test_voyage_rerank_batches_count_the_query_for_each_document(): void
    {
        $api = new VoyageRerankApi('test');
        $client = (new ReflectionProperty($api, 'client'))->getValue($api);
        $client->getConfig('handler')->setHandler(static function (RequestInterface $request): PromiseInterface {
            $body = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
            // The query has 7000 tokens. Each document adds at least one more.
            if (count($body['documents']) * 7001 > 600_000) {
                return new FulfilledPromise(new Response(400, [], '{"detail":"Total number of tokens in the batch exceeds the limit"}'));
            }

            return new FulfilledPromise(new Response(200, [], json_encode([
                'data' => array_map(
                    static fn (int $index): array => ['index' => $index, 'relevance_score' => 0.5],
                    array_keys($body['documents']),
                ),
            ], JSON_THROW_ON_ERROR)));
        });

        $result = $api->rerank(array_fill(0, 86, 'book'), str_repeat(' x', 7000));

        $this->assertSame(range(0, 85), array_column($result, 'index'));
    }

    private function mockAdapterResponses(Embedder|EmbeddingsApi|RerankApi $api, array &$requests, array $responses): void
    {
        $mock = new MockHandler($responses);
        $client = (new ReflectionProperty($api, 'client'))->getValue($api);
        $client->getConfig('handler')->setHandler(
            static function (RequestInterface $request, array $options) use ($mock, &$requests): PromiseInterface {
                $requests[] = ['path' => $request->getUri()->getPath(), ...json_decode((string) $request->getBody(), true)];

                return $mock($request, $options);
            }
        );
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

    public function test_scalar_embedding_batch_items_use_the_default_dimensions(): void
    {
        $this->assertSame(
            [['dims' => 256, 'keys' => ['first'], 'texts' => ['']]],
            batch_slots(['first' => null], 256),
        );
    }

    public function test_api_response_rejects_a_non_numeric_rerank_score(): void
    {
        $response = new Response(200, [], '{"results":[{"index":0,"relevance_score":"0.9"}]}');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('invalid relevance score');
        ApiResponse::rerank($response, 'results', 1);
    }

    public function test_api_response_requires_a_json_object(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('AI API response must contain a JSON object.');
        ApiResponse::embeddings(new Response(200, [], '[]'), 1);
    }

    /** @dataProvider invalidEmbeddingVectors */
    public function test_api_response_rejects_invalid_embedding_vectors(string $vector, string $reason): void
    {
        $response = new Response(200, [], '{"data":[{"index":0,"embedding":'.$vector.'}]}');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Embedding response contains '.$reason.'.');
        ApiResponse::embeddings($response, 1);
    }

    public static function invalidEmbeddingVectors(): array
    {
        return [
            'invalid base64' => ['"!"', 'an invalid base64 vector'],
            'empty vector' => ['[]', 'an invalid vector'],
            'string component' => ['["1"]', 'an invalid vector component'],
        ];
    }
}
