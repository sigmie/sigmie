<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use ReflectionObject;
use Sigmie\AI\APIs\CohereRerankApi;
use Sigmie\AI\APIs\InfinityRerankApi;
use Sigmie\AI\APIs\VoyageRerankApi;
use Sigmie\AI\Contracts\RerankApi;
use Sigmie\AI\Rerankers\NoopReranker;
use Sigmie\Base\Http\Responses\Search;
use Sigmie\Document\Document;
use Sigmie\Document\RerankedHit;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\Text;
use Sigmie\Search\NewRerank;
use Sigmie\Semantic\Contracts\AIProvider;
use Sigmie\Semantic\Reranker;
use Sigmie\Testing\TestCase;
use Symfony\Component\Yaml\Yaml;
use UnexpectedValueException;

class RerankerTest extends TestCase
{
    public function test_custom_reranker_cannot_return_the_same_hit_twice(): void
    {
        $api = $this->createStub(RerankApi::class);
        $api->method('rerank')->willReturn([['index' => 1, 'score' => 0.9], ['index' => 1, 'score' => 0.5]]);
        $this->expectException(UnexpectedValueException::class);

        (new NewRerank($api))->query('guide')->rerank([['_id' => 'first'], ['_id' => 'second']]);
    }

    public function test_nested_numeric_rerank_field_reads_each_record(): void
    {
        $props = new NewProperties;
        $props->nested('rows', function (NewProperties $row): void {
            $row->text('0')->semantic(api: 'e');
            $row->text('other');
        });
        $response = new Search(200, [], '{"hits":{"hits":[{"_source":{"rows":[{"0":"first","other":"secret"},{"0":"second"}]}}]}}');
        $provider = $this->semanticProvider([0.9]);

        (new Reranker($provider, $props->get()))->rerank($response, 'second');

        $this->assertSame(['rows.0: first | second | '], $provider->documents);
    }

    public function test_a_rerank_without_selected_fields_sends_the_whole_source(): void
    {
        $source = ['title' => 'Search guide', 'pages' => 12];
        $api = $this->createMock(RerankApi::class);
        $api->expects($this->once())->method('rerank')
            ->with($this->callback(fn (array $documents): bool => Yaml::parse($documents[0]) === $source), 'guide', 1)
            ->willReturn([['index' => 0, 'score' => 0.9]]);

        (new NewRerank($api))->query('guide')->rerank([['_id' => 'one', '_source' => $source]]);
    }

    public function test_top_k_is_limited_to_the_number_of_hits_sent(): void
    {
        $api = $this->createMock(RerankApi::class);
        $api->expects($this->once())->method('rerank')->with($this->isType('array'), 'guide', 1)
            ->willReturn([['index' => 0, 'score' => 0.9]]);

        $hits = (new NewRerank($api))->query('guide')->topK(5)->rerank([['_id' => 'one']]);

        $this->assertSame(['one'], array_column($hits, '_id'));
    }

    public function test_zero_top_k_returns_no_hits_without_calling_the_provider(): void
    {
        $api = $this->createMock(RerankApi::class);
        $api->expects($this->never())->method('rerank');

        $this->assertSame([], (new NewRerank($api))->query('guide')->topK(0)->rerank([['_id' => 'one']]));
    }

    public function test_rerank_returns_the_highest_provider_scores_first(): void
    {
        $api = $this->createStub(RerankApi::class);
        $api->method('rerank')->willReturn([
            ['index' => 0, 'score' => 0.1],
            ['index' => 1, 'score' => 0.9],
            ['index' => 2, 'score' => 0.5],
        ]);
        $hits = [
            ['_id' => 'one', '_score' => 3.0],
            ['_id' => 'two', '_score' => 2.0],
            ['_id' => 'three', '_score' => 1.0],
        ];

        $reranked = (new NewRerank($api))->query('guide')->topK(2)->rerank($hits);

        $this->assertSame(['two', 'three'], array_column($reranked, '_id'));
        $this->assertSame([0.9, 0.5], array_column($reranked, '_rerank_score'));
        $this->assertSame([2.0, 1.0], array_column($reranked, '_score'));
    }

    public function test_a_blank_query_leaves_hits_unchanged(): void
    {
        $api = $this->createMock(RerankApi::class);
        $api->expects($this->never())->method('rerank');
        $hits = [['_id' => 'one']];

        $this->assertSame($hits, (new NewRerank($api))->query(" \t\u{00a0}")->rerank($hits));
    }

    public function test_rerank_adapters_send_sparse_document_arrays_as_lists(): void
    {
        foreach ($this->realRerankApis() as $api) {
            $results = $api->rerank([3 => 'title: Laravel', 8 => 'title: Django'], 'best framework');

            $this->assertSame([1, 0], array_column($results, 'index'));
        }
    }

    public function test_semantic_reranked_hits_expose_both_scores(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'e', dimensions: 128);
        $response = new Search(200, [], json_encode(['hits' => ['hits' => [[
            '_id' => 'a', '_score' => 2.0, '_source' => ['title' => 'Guide'],
        ]]]], JSON_THROW_ON_ERROR));

        [$hit] = (new Reranker($this->semanticProvider([0.75]), $props->get()))->rerank($response, 'guide')->hits();

        $this->assertInstanceOf(RerankedHit::class, $hit);
        $this->assertSame(2.0, $hit->_score);
        $this->assertSame(0.75, $hit->_rerank_score);
    }

    public function test_semantic_rerank_does_not_invent_scores_for_missing_results(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'e', dimensions: 128);
        $response = new Search(200, [], json_encode(['hits' => ['total' => ['value' => 8], 'hits' => [
            ['_id' => 'unscored', '_source' => ['title' => 'A']],
            ['_id' => 'scored', '_source' => ['title' => 'B']],
        ]]], JSON_THROW_ON_ERROR));

        $reranked = (new Reranker($this->semanticProvider([1 => 0.0]), $props->get()))->rerank($response, 'B');

        $this->assertSame(8, $reranked->total());
        $this->assertSame(['scored'], array_column($reranked->json('hits.hits'), '_id'));
        $this->assertSame(0.0, $reranked->json('hits.hits.0._rerank_score'));
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
            'test_nested_numeric_rerank_field_reads_each_record',
            'test_custom_reranker_cannot_return_the_same_hit_twice',
            'test_top_k_is_limited_to_the_number_of_hits_sent',
            'test_zero_top_k_returns_no_hits_without_calling_the_provider',
            'test_rerank_returns_the_highest_provider_scores_first',
            'test_a_blank_query_leaves_hits_unchanged',
            'test_rerank_adapters_send_sparse_document_arrays_as_lists',
            'test_semantic_reranked_hits_expose_both_scores',
            'test_semantic_rerank_does_not_invent_scores_for_missing_results',
            'reranking_accepts_nested_arrays_of_text',
            'noop_honors_the_requested_result_limit',
            'reranking_preserves_highlights_and_stored_fields',
            'rerank_sends_plain_strings_from_a_semantic_array',
            'rerank_keeps_sort_and_matches',
            'rerank_scores_returned_hits_without_a_total',
            'rerank_reads_text_through_a_nested_list',
            'test_legacy_rerank_reads_scalar_values_from_text_arrays',
        ], true);
    }

    public function test_legacy_rerank_reads_scalar_values_from_text_arrays(): void
    {
        $props = new NewProperties;
        $props->text('tags')->semantic(api: 'e', dimensions: 128);
        $response = new Search(200, [], '{"hits":{"hits":[{"_id":"a","_index":"docs","_source":{"tags":[42,false,"book"]}}]}}');
        $provider = $this->semanticProvider([0.9]);

        (new Reranker($provider, $props->get()))->rerank($response, 'book');

        $this->assertSame(['tags: 42 | false | book | '], $provider->documents);
        $this->assertSame('a', $response->hits()[0]->_id);
    }

    /** @test */
    public function reranking_accepts_nested_arrays_of_text(): void
    {
        $props = new NewProperties;
        $props->text('tags')->semantic(api: 'e', dimensions: 128);
        $response = new Search(200, [], json_encode(['hits' => ['hits' => [[
            '_id' => 'a', '_index' => 'docs', '_score' => 1, '_source' => ['tags' => ['A', ['B', 'C']]],
        ]]]], JSON_THROW_ON_ERROR));
        $provider = $this->semanticProvider([0.9]);
        (new Reranker($provider, $props->get()))->rerank($response, 'B');
        $this->assertSame(['tags: A | B | C | '], $provider->documents);
    }

    /** @test */
    public function noop_honors_the_requested_result_limit(): void
    {
        $reranker = new NoopReranker;
        $this->assertSame(['a'], $reranker->rerank(['a', 'b'], 'query', 1));
    }

    /** @test */
    public function reranking_preserves_highlights_and_stored_fields(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'e', dimensions: 128);
        $raw = ['_id' => 'a', '_index' => 'docs', '_score' => 1, '_source' => ['title' => 'A'],
            'highlight' => ['title' => ['<em>A</em>']], 'fields' => ['edition' => [2]]];
        $response = new Search(200, [], json_encode(['hits' => ['hits' => [$raw]]], JSON_THROW_ON_ERROR));
        (new Reranker($this->semanticProvider([0.9]), $props->get()))->rerank($response, 'A');
        $this->assertSame($raw['highlight'], $response->json('hits.hits.0.highlight'));
        $this->assertSame($raw['fields'], $response->json('hits.hits.0.fields'));
    }

    /** @test */
    public function rerank_scores_returned_hits_without_a_total(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'e', dimensions: 128);
        $response = new Search(200, [], json_encode(['hits' => ['hits' => [
            ['_index' => 'docs', '_id' => 'a', '_source' => ['title' => 'A'], '_score' => 1],
            ['_index' => 'docs', '_id' => 'b', '_source' => ['title' => 'B'], '_score' => 1],
        ]]], JSON_THROW_ON_ERROR));

        $reranked = (new Reranker($this->semanticProvider([0.2, 0.9]), $props->get()))
            ->rerank($response, 'B');

        $this->assertSame(['b', 'a'], array_column($reranked->json('hits.hits'), '_id'));
    }

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
    public function new_rerank_accepts_raw_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');
        $blueprint->text('description');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Laravel', 'description' => 'PHP web framework'], _id: 'laravel'),
                new Document(['title' => 'Django', 'description' => 'Python web framework'], _id: 'django'),
            ]);

        $rawHits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('framework')
            ->fields(['description'])
            ->size(2)
            ->get()
            ->json('hits');

        $reranked = (new NewRerank($this->rerankApi))
            ->fields(['title', 'description'])
            ->query('Laravel framework')
            ->topK(1)
            ->rerank($rawHits);

        $this->assertCount(1, $reranked);
        $this->assertInstanceOf(RerankedHit::class, $reranked[0]);
        $this->assertSame('laravel', $reranked[0]->_id);
        $this->rerankApi->assertRerankWasCalledWith('Laravel framework', 1);
    }

    /**
     * @test
     */
    public function new_rerank_leaves_empty_elasticsearch_hits_untouched(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $rawHits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('missing')
            ->fields(['title'])
            ->get()
            ->json('hits');

        $reranked = (new NewRerank($this->rerankApi))
            ->fields(['title'])
            ->query('missing')
            ->rerank($rawHits);

        $this->assertSame([], $rawHits);
        $this->assertSame([], $reranked);
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

    /**
     * @test
     */
    public function rerank_sends_plain_strings_from_a_semantic_array(): void
    {
        $blueprint = new NewProperties;
        $blueprint->longText('experience')->semantic(dimensions: 384, api: 'test-embeddings');

        $provider = $this->semanticProvider([0.9]);
        $search = new Search(200, ['Content-Type' => 'application/json'], json_encode([
            'hits' => [
                'total' => ['value' => 1],
                'hits' => [[
                    '_index' => 'professionals',
                    '_id' => '1',
                    '_score' => 1,
                    '_source' => [
                        'experience' => ['Artist', 'Graphic Design', 'Creative Director'],
                    ],
                ]],
            ],
        ]));

        (new Reranker($provider, $blueprint->get()))->rerank($search, 'drawing');

        $this->assertSame(
            'experience: Artist | Graphic Design | Creative Director | ',
            $provider->documents[0]
        );
    }

    /**
     * @test
     */
    public function real_rerank_apis_reorder_elasticsearch_hits(): void
    {
        foreach ($this->realRerankApis() as $apiName => $api) {
            $indexName = uniqid();

            $blueprint = new NewProperties;
            $blueprint->longText('title')->semantic(dimensions: 384, api: 'test-embeddings');
            $blueprint->longText('description')->semantic(dimensions: 384, api: 'test-embeddings');

            $this->sigmie->newIndex($indexName)
                ->properties($blueprint)
                ->create();

            $this->sigmie->collect($indexName, refresh: true)
                ->properties($blueprint)
                ->merge([
                    new Document(['title' => 'Laravel', 'description' => 'PHP web framework'], _id: $apiName.'-laravel'),
                    new Document(['title' => 'Django', 'description' => 'Python web framework'], _id: $apiName.'-django'),
                    new Document(['title' => 'React', 'description' => 'JavaScript interface library'], _id: $apiName.'-react'),
                ]);

            $response = $this->sigmie->newSearch($indexName)
                ->properties($blueprint)
                ->queryString('framework')
                ->semantic()
                ->fields(['title', 'description'])
                ->size(3)
                ->get();

            $this->assertGreaterThanOrEqual(2, $response->total());

            $directScores = $api->rerank(['title: Laravel', 'title: Django'], 'best framework');

            $this->assertSame(1, $directScores[0]['index']);
            $this->assertSame(0.96, $directScores[0]['score']);

            $reranked = $response->rerank($api, ['title', 'description'], 'best framework', 2);

            $this->assertCount(2, $reranked);
            $this->assertSame($apiName.'-django', $reranked[0]->_id);
            $this->assertSame('Django', $reranked[0]->_source['title']);
            $this->assertSame(0.96, $reranked[0]->_rerank_score);
            $this->assertSame($apiName.'-laravel', $reranked[1]->_id);
        }
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

    private function realRerankApis(): array
    {
        $cohere = new CohereRerankApi('test-key');
        $infinity = new InfinityRerankApi;
        $voyage = new VoyageRerankApi('test-key');

        return [
            'cohere' => $this->withMockRerankClient($cohere, 'cohere'),
            'infinity' => $this->withMockRerankClient($infinity, 'infinity'),
            'voyage' => $this->withMockRerankClient($voyage, 'voyage'),
        ];
    }

    private function withMockRerankClient(RerankApi $api, string $provider): RerankApi
    {
        $handler = function (RequestInterface $request) use ($provider): Promise {
            $payload = json_decode((string) $request->getBody(), true);
            $topK = $payload['top_n'] ?? $payload['top_k'] ?? null;
            $documents = $payload['documents'] ?? [];
            $results = [];

            foreach ($documents as $index => $document) {
                $results[] = [
                    'index' => $index,
                    'relevance_score' => match (true) {
                        str_contains($document, 'Django') => 0.96,
                        str_contains($document, 'Laravel') => 0.82,
                        default => 0.11,
                    },
                ];
            }

            usort($results, fn (array $left, array $right): int => $right['relevance_score'] <=> $left['relevance_score']);

            if ($topK !== null) {
                $results = array_slice($results, 0, (int) $topK);
            }

            $promise = new Promise;
            $promise->resolve(new Response(200, [], json_encode(
                match ($provider) {
                    'voyage' => ['data' => $results],
                    default => ['results' => $results],
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

    /**
     * @test
     */
    public function rerank_keeps_sort_and_matches(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'e', dimensions: 128);
        $search = new Search(200, ['Content-Type' => 'application/json'], json_encode([
            'hits' => [
                'total' => ['value' => 1],
                'hits' => [[
                    '_index' => 'docs',
                    '_id' => '1',
                    '_score' => 1.5,
                    'sort' => [1.5, '1'],
                    '_matches' => [
                        'comments' => [
                            'total' => 2,
                            'items' => [
                                ['body' => 'kept match'],
                            ],
                        ],
                    ],
                    '_source' => ['title' => 'Hello'],
                ]],
            ],
        ]));

        $hit = (new Reranker($this->scoringProvider(), $props->get()))->rerank($search, 'Hello')->hits()[0];

        $this->assertSame([1.5, '1'], $hit->sort);
        $this->assertSame([['body' => 'kept match']], $hit->matches('comments'));
    }

    /**
     * @test
     */
    public function rerank_reads_text_through_a_nested_list(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'e', dimensions: 128);
        $props->nested('comments', function (NewProperties $props): void {
            $props->text('body')->semantic(api: 'e', dimensions: 128);
        });
        $provider = $this->scoringProvider();
        $search = new Search(200, ['Content-Type' => 'application/json'], json_encode([
            'hits' => [
                'total' => ['value' => 1],
                'hits' => [[
                    '_index' => 'docs',
                    '_id' => '1',
                    '_score' => 1,
                    '_source' => [
                        'title' => 'Hello',
                        'comments' => [
                            ['body' => 'First nested body'],
                            ['body' => 'Second nested body'],
                        ],
                    ],
                ]],
            ],
        ]));

        (new Reranker($provider, $props->get()))->rerank($search, 'nested');

        $this->assertStringContainsString('First nested body', $provider->documents[0]);
        $this->assertStringContainsString('Second nested body', $provider->documents[0]);
    }

    private function scoringProvider(): AIProvider
    {
        return new class implements AIProvider
        {
            public array $documents = [];

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

                return [0 => 0.9];
            }
        };
    }

    public function test_rerank_omits_null_inputs_and_keeps_hits_at_the_threshold(): void
    {
        $props = new NewProperties;
        $props->text('title')->semantic('model');
        $provider = $this->semanticProvider([0.49, 0.5]);
        $search = new Search(200, [], json_encode(['hits' => ['hits' => [
            ['_id' => 'low', '_source' => ['title' => [null, 'book']]],
            ['_id' => 'boundary', '_source' => ['title' => 'guide']],
        ]]]));
        $hits = (new Reranker($provider, $props->get(), 0.5))->rerank($search, 'book')->hits();
        $this->assertSame(['title: book | ', 'title: guide'], $provider->documents);
        $this->assertSame(['boundary'], array_map(fn ($hit): string => $hit->_id, $hits));
    }
}
