<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Http\Promise\Promise;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse as ElasticsearchResponseContract;
use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Base\Drivers\Elasticsearch;
use Sigmie\Base\Http\ElasticsearchConnection;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Base\Http\Responses\Mget;
use Sigmie\Document\Document;
use Sigmie\Document\Hit;
use Sigmie\Http\NdJSONRequest;
use Sigmie\Mappings\NewProperties;
use Sigmie\Query\Search;
use Sigmie\Search\MMR;
use Sigmie\Search\NewRecommendations;

class RecommendationsCorrectnessTest extends TestCase
{
    public function test_a_zero_rank_window_returns_no_recommendations(): void
    {
        $connection = $this->respondingConnection([array_fill(0, 128, 0.1)]);
        $properties = new NewProperties;
        $properties->text('title')->semantic(api: 'embeddings', dimensions: 128, accuracy: 3);

        $hits = (new NewRecommendations('products', $connection))
            ->properties($properties)
            ->field('title')
            ->seedIds(['seed-1'])
            ->rrf(rankWindowSize: 0)
            ->hits();

        $this->assertSame([], $hits);
        $this->assertSame('', $connection->body);
    }

    public function test_changing_only_the_rrf_constant_keeps_the_default_candidate_window(): void
    {
        $connection = $this->respondingConnection([array_fill(0, 128, 0.1)]);
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'embeddings', dimensions: 128, accuracy: 3);

        (new NewRecommendations('products', $connection))
            ->properties($props)->field('title')->seedIds(['seed-1'])
            ->rrf(rrfRankConstant: 80)->topK(5)->hits();

        [, $query] = NdJSONRequest::decode($connection->body);
        $this->assertSame(50, $query['size']);
    }

    /** @test */
    public function make_uses_each_seed_vector_and_excludes_all_seed_ids(): void
    {
        $vectors = [array_fill(0, 128, 0.1), array_fill(0, 128, 0.2)];
        $connection = $this->respondingConnection($vectors);
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'embeddings', dimensions: 128, accuracy: 3);
        $props->keyword('brand');

        $recommendations = (new NewRecommendations('products', $connection))
            ->properties($props)
            ->field('title')
            ->seedIds(['seed-1', 'seed-2'])
            ->filter("brand:'a' OR brand:'b'")
            ->topK(3);

        $query = $recommendations->make()->toRaw();
        $this->assertSame($vectors, $this->valuesOf($query, 'query_vector'));
        $this->assertSame([], $this->valuesOf($query, 'match_all'));
        $this->assertSame(3, $query['size']);
        foreach ($query['knn'] as $clause) {
            $this->assertSame(['seed-1', 'seed-2'], $this->valuesOf($clause['filter'], 'ids')[0]['values']);
            $this->assertTrue($this->seedCoversBothBrands($clause['filter']));
        }

        $this->assertSame($vectors, $this->valuesOf($recommendations->make()->toRaw(), 'query_vector'));
    }

    /** @test */
    public function rrf_rank_window_sets_the_size_of_each_seed_search(): void
    {
        $vector = array_fill(0, 128, 0.1);
        $connection = $this->respondingConnection([$vector]);
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'embeddings', dimensions: 128, accuracy: 3);

        (new NewRecommendations('products', $connection))
            ->properties($props)
            ->field('title')
            ->seedIds(['seed-1'])
            ->rrf(rankWindowSize: 7)
            ->topK(2)
            ->hits();

        [, $query] = NdJSONRequest::decode($connection->body);
        $this->assertSame(7, $query['size']);
    }

    /**
     * @test
     */
    public function hits_sends_the_accuracy_7_vector(): void
    {
        [, $query] = $this->recommendation();

        $this->assertSame(20, $query['size']);
        $this->assertSame([array_fill(0, 256, 0.25)], $this->valuesOf($query, 'query_vector'));
    }

    /**
     * @test
     */
    public function hits_excludes_a_seed_id_that_contains_a_quote(): void
    {
        [$hits, $query] = $this->recommendation();

        $this->assertSame(["a'b", 'seed-1'], $this->valuesOf($query, 'ids')[0]['values']);
        $this->assertSame(['other'], array_map(fn (Hit $hit): string => $hit->_id, $hits));
    }

    /**
     * @test
     */
    public function make_returns_a_search_when_seed_is_missing(): void
    {
        $properties = new NewProperties;
        $properties->text('title');

        $search = (new NewRecommendations('products', $this->offlineConnection()))
            ->properties($properties)
            ->field('title', 2.0)
            ->make();

        $this->assertInstanceOf(Search::class, $search);
        $this->assertArrayHasKey('match_none', $search->toRaw()['query']);
    }

    /**
     * @test
     */
    public function field_weights_do_not_scale_the_rrf_score(): void
    {
        $connection = new class extends ElasticsearchConnection
        {
            public function __construct() {}

            public function driver(): SearchEngine
            {
                return new Elasticsearch;
            }

            public function isServerless(): bool
            {
                return false;
            }

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponseContract
            {
                $hit = [
                    'took' => 1,
                    'hits' => [
                        'total' => ['value' => 1, 'relation' => 'eq'],
                        'hits' => [[
                            '_index' => 'products',
                            '_id' => 'other',
                            '_score' => 1.0,
                            '_source' => ['category' => 'Shoes', 'name' => 'Runner'],
                        ]],
                    ],
                ];

                return new ElasticsearchResponse(200, [], json_encode([
                    'responses' => [$hit, $hit],
                ], JSON_THROW_ON_ERROR));
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                throw new RuntimeException('The recommendation must not call Elasticsearch asynchronously.');
            }
        };

        $properties = new NewProperties;
        $properties->text('category')->semantic(api: 'embeddings', dimensions: 128, accuracy: 1);
        $properties->text('name')->semantic(api: 'embeddings', dimensions: 128, accuracy: 1);

        $vector = array_fill(0, 128, 0.1);
        $document = fn (string $id): Document => new Document([
            'category' => 'Shoes',
            'name' => 'Runner',
            '_embeddings' => [
                'category' => ['v' => $vector],
                'name' => ['v' => $vector],
            ],
        ], $id);

        $recommendations = (new NewRecommendations('products', $connection))
            ->properties($properties)
            ->field('category', weight: 2.0)
            ->field('name')
            ->topK(5);

        (new ReflectionProperty(NewRecommendations::class, 'docs'))->setValue($recommendations, [
            $document('product-123'),
            $document('product-456'),
        ]);
        (new ReflectionProperty(NewRecommendations::class, 'seedIds'))->setValue($recommendations, [
            'product-123',
            'product-456',
        ]);

        $hits = $recommendations->hits();

        $this->assertEqualsWithDelta((1 / 61) + (1 / 61), $hits[0]->_score, 0.0000000001);
    }

    /**
     * @test
     */
    public function a_seed_without_embeddings_returns_no_hits(): void
    {
        $connection = new class extends ElasticsearchConnection
        {
            public string $body = '';

            public function __construct() {}

            public function driver(): SearchEngine
            {
                return new Elasticsearch;
            }

            public function isServerless(): bool
            {
                return false;
            }

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponseContract
            {
                $this->body = (string) $request->getBody();

                return new ElasticsearchResponse(200, [], json_encode([
                    'responses' => [[
                        'took' => 1,
                        'hits' => [
                            'total' => ['value' => 1, 'relation' => 'eq'],
                            'hits' => [[
                                '_index' => 'products',
                                '_id' => 'other',
                                '_score' => 1.0,
                                '_source' => ['title' => 'Other'],
                            ]],
                        ],
                    ]],
                ], JSON_THROW_ON_ERROR));
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                throw new RuntimeException('The recommendation must not call Elasticsearch asynchronously.');
            }
        };

        $properties = new NewProperties;
        $properties->text('title')->semantic(api: 'embeddings', dimensions: 128, accuracy: 1);

        $recommendations = (new NewRecommendations('products', $connection))
            ->properties($properties)
            ->field('title')
            ->topK(5);

        (new ReflectionProperty(NewRecommendations::class, 'docs'))->setValue($recommendations, [
            new Document(['title' => 'Seed'], 'seed-1'),
        ]);
        (new ReflectionProperty(NewRecommendations::class, 'seedIds'))->setValue($recommendations, ['seed-1']);

        $hits = $recommendations->hits();

        $this->assertSame([], $hits);
        $this->assertSame('', $connection->body);
    }

    /**
     * @test
     */
    public function an_or_filter_still_excludes_the_seed_on_every_branch(): void
    {
        $connection = $this->respondingConnection();
        $props = new NewProperties;
        $props->keyword('brand');
        $props->text('title')->semantic(api: 'embeddings', dimensions: 128, accuracy: 3);

        $recommendations = (new NewRecommendations('products', $connection))
            ->properties($props)
            ->field('title')
            ->filter("brand:'a' OR brand:'b'")
            ->topK(1);

        $document = new Document([
            'title' => 'Seed',
            '_embeddings' => ['title' => ['v' => array_fill(0, 128, 0.1)]],
        ], 'seed-1');

        (new ReflectionProperty(NewRecommendations::class, 'docs'))->setValue($recommendations, [$document]);
        (new ReflectionProperty(NewRecommendations::class, 'seedIds'))->setValue($recommendations, ['seed-1']);

        $recommendations->hits();

        $lines = array_values(array_filter(NdJSONRequest::decode($connection->body), is_array(...)));

        $this->assertTrue($this->seedCoversBothBrands($lines[1]['knn'][0]['filter']));
    }

    private function seedCoversBothBrands(array $node): bool
    {
        $must = $node['bool']['must'] ?? $node['bool']['filter'] ?? null;

        if (is_array($must)) {
            $hasSeed = false;
            $brands = [];

            foreach ($must as $clause) {
                if ($this->nodeExcludesSeed($clause)) {
                    $hasSeed = true;
                }

                $this->collectBrands($clause, $brands);
            }

            if ($hasSeed && isset($brands['a'], $brands['b'])) {
                return true;
            }
        }

        foreach ($node as $child) {
            if (is_array($child) && $this->seedCoversBothBrands($child)) {
                return true;
            }
        }

        return false;
    }

    private function collectBrands(array $node, array &$brands): void
    {
        $brand = $node['term']['brand']['value'] ?? null;

        if (is_string($brand)) {
            $brands[$brand] = true;
        }

        foreach ($node as $child) {
            if (is_array($child)) {
                $this->collectBrands($child, $brands);
            }
        }
    }

    private function nodeExcludesSeed(array $node): bool
    {
        $mustNot = $node['bool']['must_not'] ?? $node['must_not'] ?? null;

        return is_array($mustNot) && str_contains((string) json_encode($mustNot), 'seed-1');
    }

    /**
     * @return array{0: array, 1: array}
     */
    private function recommendation(): array
    {
        $vector = array_fill(0, 256, 0.25);
        $connection = $this->respondingConnection();
        $properties = new NewProperties;
        $properties->text('title')->semantic(api: 'test-embeddings', accuracy: 7, dimensions: 256);

        $recommendations = (new NewRecommendations('products', $connection))
            ->properties($properties)
            ->field('title')
            ->topK(2);

        $document = new Document([
            'title' => 'Seed',
            '_embeddings' => [
                'title' => [
                    'exact_dims256_cosine_script' => [
                        ['vector' => $vector],
                    ],
                ],
            ],
        ], "a'b");

        (new ReflectionProperty(NewRecommendations::class, 'docs'))->setValue($recommendations, [$document]);
        (new ReflectionProperty(NewRecommendations::class, 'seedIds'))->setValue($recommendations, ["a'b", 'seed-1']);

        $hits = $recommendations->hits();

        $lines = array_values(array_filter(
            NdJSONRequest::decode($connection->body),
            is_array(...),
        ));

        return [$hits, $lines[1]];
    }

    private function valuesOf(array $node, string $key): array
    {
        $found = [];

        foreach ($node as $name => $value) {
            if ($name === $key) {
                $found[] = $value;
            }

            if (is_array($value)) {
                $found = [...$found, ...$this->valuesOf($value, $key)];
            }
        }

        return $found;
    }

    private function respondingConnection(array $vectors = []): ElasticsearchConnection
    {
        return new class($vectors) extends ElasticsearchConnection
        {
            public string $body = '';

            public function __construct(private array $vectors) {}

            public function driver(): SearchEngine
            {
                return new Elasticsearch;
            }

            public function isServerless(): bool
            {
                return false;
            }

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponseContract
            {
                if (str_ends_with($request->getUri()->getPath(), '/_mget')) {
                    $ids = json_decode((string) $request->getBody(), true)['docs'];
                    $docs = array_map(fn (array $id, array $vector): array => [
                        ...$id,
                        '_index' => 'products',
                        'found' => true,
                        '_source' => ['_embeddings' => ['title' => array_is_list($vector) ? ['v' => $vector] : $vector]],
                    ], $ids, $this->vectors);

                    return new Mget(200, [], json_encode(['docs' => $docs], JSON_THROW_ON_ERROR));
                }

                $this->body = (string) $request->getBody();

                return new ElasticsearchResponse(200, [], json_encode([
                    'responses' => [[
                        'took' => 1,
                        'hits' => [
                            'total' => ['value' => 1, 'relation' => 'eq'],
                            'hits' => [[
                                '_index' => 'products',
                                '_id' => 'other',
                                '_score' => 1.0,
                                '_source' => ['title' => 'Other'],
                            ]],
                        ],
                    ]],
                ], JSON_THROW_ON_ERROR));
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                throw new RuntimeException('The recommendation must not call Elasticsearch asynchronously.');
            }
        };
    }

    private function offlineConnection(): ElasticsearchConnection
    {
        return new class extends ElasticsearchConnection
        {
            public function __construct() {}

            public function driver(): SearchEngine
            {
                return new Elasticsearch;
            }

            public function isServerless(): bool
            {
                return false;
            }

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponseContract
            {
                throw new RuntimeException('make() must not call Elasticsearch.');
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                throw new RuntimeException('make() must not call Elasticsearch.');
            }
        };
    }

    public function test_seed_vectors_query_only_their_own_mapped_vector_fields(): void
    {
        $props = new NewProperties;
        $title = $props->text('title');
        $title->semantic(api: 'first-model', dimensions: 128, accuracy: 3);
        $title->semantic(api: 'second-model', dimensions: 128, accuracy: 3);
        [$first, $second] = $title->vectorFields()->toArray();
        $vectors = [$first->name => array_fill(0, 128, 0.1), $second->name => array_fill(0, 128, 0.2)];
        $connection = $this->respondingConnection([$vectors]);

        $query = (new NewRecommendations('products', $connection))->properties($props)->field('title')
            ->seedIds(['seed'])->make()->toRaw();

        $this->assertSame([
            '_embeddings.title.'.$first->name => $vectors[$first->name],
            '_embeddings.title.'.$second->name => $vectors[$second->name],
        ], array_column($query['knn'], 'query_vector', 'field'));
        $this->assertCount(2, $query['knn']);
    }

    public function test_seed_exclusion_preserves_control_characters_in_ids(): void
    {
        $connection = $this->respondingConnection([array_fill(0, 128, 0.1)]);
        $props = new NewProperties;
        $props->text('title')->semantic(api: 'embeddings', dimensions: 128, accuracy: 3);
        $seedId = "seed\x03id";

        $query = (new NewRecommendations('products', $connection))->properties($props)->field('title')
            ->seedIds([$seedId])->make()->toRaw();

        $this->assertSame([$seedId], $this->valuesOf($query['knn'][0]['filter'], 'ids')[0]['values']);
    }

    public function test_literal_dotted_embedding_in_recommendation_ranking(): void
    {
        $hit = static fn (string $id, array $vector): array => [
            '_id' => $id, '_source' => ['_embeddings' => ['details' => ['part.name' => $vector]]],
        ];
        $ranked = (new MMR)->diversify(
            [$hit('other', [0, 1]), $hit('similar', [1, 0])],
            [$hit('seed', [1, 0])],
            'details.part.name',
            1,
        );
        $this->assertSame('similar', $ranked[0]['_id']);
    }
}
