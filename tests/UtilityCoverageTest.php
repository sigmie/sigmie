<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Exception;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Http\Promise\FulfilledPromise;
use Http\Promise\Promise;
use PHPUnit\Framework\AssertionFailedError;
use Sigmie\Base\APIs\Explain;
use Sigmie\Base\APIs\Stats;
use Sigmie\Base\APIs\Update as UpdateApi;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Base\Drivers\Opensearch;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Base\Http\ElasticsearchResponse as HttpElasticsearchResponse;
use Sigmie\Base\Http\PointInTimeRequests;
use Sigmie\Document\Document;
use Sigmie\Document\Hit;
use Sigmie\Enums\VectorSimilarity;
use Sigmie\Index\Analysis\SimpleAnalyzer;
use Sigmie\Index\Analysis\TokenFilter\LanguageStemmer;
use Sigmie\Index\Analysis\TokenFilter\Shingle;
use Sigmie\Index\Settings;
use Sigmie\Mappings\ElasticsearchMappingType;
use Sigmie\Mappings\Field;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\NewSemanticField;
use Sigmie\Mappings\Traits\HasFacets;
use Sigmie\Mappings\Traits\HasQueries;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Mappings\Types\HTML as HtmlField;
use Sigmie\Mappings\Types\Id as IdField;
use Sigmie\Mappings\Types\Image;
use Sigmie\Mappings\Types\Name as NameField;
use Sigmie\Query\Aggregations\Bucket\Missing;
use Sigmie\Query\Aggregations\Metrics\Rate;
use Sigmie\Query\Aggs;
use Sigmie\Search\MMR;
use Sigmie\Search\PitSortPlanner;
use Sigmie\Search\PointInTimeIterator;
use Sigmie\Search\RawQuery;
use Sigmie\Semantic\Providers\AbstractAIProvider;
use Sigmie\Semantic\Providers\Noop;
use Sigmie\Shared\UsesApis;
use Sigmie\Sigmie;
use Sigmie\Testing\TestCase;

class UtilityCoverageTest extends TestCase
{
    /**
     * @test
     */
    public function small_mapping_query_and_provider_helpers_are_backed_by_elasticsearch_results(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Utility coverage'], _id: 'matching'),
                new Document(['title' => 'Other document'], _id: 'missing'),
            ]);

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->fields(['title'])
            ->queryString('Utility')
            ->hits();

        $this->assertSame(['matching'], array_map(fn ($hit): string => $hit->_id, $hits));

        $field = new Field('metadata', 'object', ['enabled' => false]);

        $this->assertSame('metadata', $field->name());
        $this->assertSame([], $field->queries('anything'));
        $this->assertSame([
            'metadata' => [
                'type' => 'object',
                'enabled' => false,
            ],
        ], $field->toRaw());

        $queryable = new class
        {
            use HasQueries;
        };

        $this->assertSame([], $queryable->queries('search'));
        $this->assertSame([], $queryable->queryStringQueries('search'));
        $this->assertSame($queryable, $queryable->withQueries(fn (string $query): array => [$query]));
        $this->assertSame(['search'], $queryable->queryStringQueries('search'));
        $this->assertSame(['manual'], $queryable->queriesFromCallback('manual'));

        $this->assertSame([
            'missing_title' => [
                'missing' => [
                    'field' => 'title',
                ],
            ],
        ], (new Missing('missing_title', 'title'))->toRaw());

        $this->assertSame([
            'yearly_rate' => [
                'rate' => [
                    'unit' => 'year',
                ],
            ],
        ], (new Rate('yearly_rate', 'created_at'))->toRaw());

        $provider = new class extends AbstractAIProvider
        {
            public function embed(mixed $item): array
            {
                return ['embedded' => $item];
            }
        };

        $this->assertSame([], $provider->rerank([['title' => 'Utility']], 'Utility'));
        $this->assertSame([
            ['embedded' => 'first'],
            ['embedded' => 'second'],
        ], $provider->batchEmbed(['first', 'second']));
        $this->assertSame(0.0, $provider->threshold());

        $noop = new Noop;
        $title = $blueprint->get()->get('title');

        $this->assertSame([-1], $noop->embed('Utility', $title));
        $this->assertSame([], $noop->queries('Utility', $title));
        $this->assertInstanceOf(DenseVector::class, $noop->type($title));

        $semantic = new NewSemanticField('title');

        $this->assertSame($semantic, $semantic->similarity(VectorSimilarity::Euclidean));
        $this->assertSame($semantic, $semantic->efConstruction(123));
        $this->assertSame($semantic, $semantic->m(12));
        $this->assertSame($semantic, $semantic->dimensions(128));

        $settings = (new Settings(primaryShards: 1, replicaShards: 0))
            ->defaultPipeline('ingest-pipeline')
            ->config('refresh_interval', '1s');

        $this->assertSame(1, $settings->primaryShards());
        $this->assertSame(0, $settings->replicaShards());
        $this->assertSame('ingest-pipeline', $settings->toRaw()['default_pipeline']);
        $this->assertSame('1s', $settings->toRaw()['refresh_interval']);

        $simpleAnalyzer = new SimpleAnalyzer;

        $this->assertSame('simple', $simpleAnalyzer->name());
        $this->assertSame([
            'simple' => [
                'type' => 'simple',
            ],
        ], $simpleAnalyzer->toRaw());

        $shingle = Shingle::fromRaw([
            'phrase_shingles' => [
                'type' => 'shingle',
                'min_shingle_size' => 2,
                'max_shingle_size' => 3,
            ],
        ]);

        $this->assertSame('shingle', $shingle->type());
        $this->assertSame([
            'phrase_shingles' => [
                'min_shingle_size' => 2,
                'max_shingle_size' => 3,
                'type' => 'shingle',
            ],
        ], $shingle->toRaw());

        $stemmer = new class extends LanguageStemmer
        {
            public static function fromRaw(array $raw): static
            {
                return new self;
            }

            public function toRaw(): array
            {
                return [
                    $this->name() => [
                        ...$this->value(),
                        'type' => $this->type(),
                    ],
                ];
            }

            public function language(): string
            {
                return 'english';
            }
        };

        $this->assertSame('stemmer', $stemmer->type());
        $this->assertSame('language', $stemmer->name());
        $this->assertSame(['language' => 'english'], $stemmer->value());

        $this->assertTrue(ElasticsearchMappingType::KEYWORD->isKeyword('keyword'));
        $this->assertTrue(ElasticsearchMappingType::INTEGER->isInteger('integer'));
        $this->assertTrue(ElasticsearchMappingType::LONG->isLong('long'));
        $this->assertTrue(ElasticsearchMappingType::FLOAT->isFloat('float'));
        $this->assertTrue(ElasticsearchMappingType::BOOLEAN->isBoolean('boolean'));
        $this->assertTrue(ElasticsearchMappingType::DATE->isDate('date'));

        Sigmie::registerPlugins(['elastiknn']);

        $this->assertTrue(Sigmie::isPluginRegistered('elastiknn'));
        $this->assertFalse(Sigmie::isPluginRegistered('missing-plugin'));

        $image = new Image('photo');

        $this->assertSame('image', $image->embeddingsType());
        $this->assertSame([], $image->queries('photo'));
        $this->assertSame([true, ''], $image->validate('photo', 'https://example.com/photo.jpg'));
        $this->assertSame([false, 'The field photo mapped as image must be a string (URL, base64, or file path)'], $image->validate('photo', 123));
        $this->assertSame([false, 'The field photo contains a non-string value in the array'], $image->validate('photo', ['https://example.com/photo.jpg', 123]));

        $image->semantic('test-clip', accuracy: 1, dimensions: 128);

        $this->assertSame([true, ''], $image->validate('photo', ['https://example.com/photo.jpg']));
        $this->assertSame(
            [false, 'The field photo contains an invalid image source: not-an-image. Must be a URL, base64 string, or existing file path.'],
            $image->validate('photo', 'not-an-image')
        );

        $mmr = new MMR(lambda: 0.7);
        $seedDocs = [
            [
                '_source' => [
                    '_embeddings' => [
                        'title' => [
                            'vector' => [1.0, 0.0],
                        ],
                    ],
                ],
            ],
        ];
        $hitsWithVectors = [
            [
                '_id' => 'alpha',
                '_source' => [
                    '_embeddings' => [
                        'title' => [
                            'vector' => [1.0, 0.0],
                        ],
                    ],
                ],
            ],
            [
                '_id' => 'beta',
                '_source' => [
                    '_embeddings' => [
                        'title' => [
                            'vector' => [0.0, 1.0],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame([], $mmr->diversify([], $seedDocs, 'title', 2));
        $this->assertSame([$hitsWithVectors[0]], $mmr->diversify([$hitsWithVectors[0]], [], 'title', 1));
        $this->assertSame(['alpha', 'beta'], array_column($mmr->diversify($hitsWithVectors, $seedDocs, 'title', 2), '_id'));

        $hitsWithoutVectors = [
            [
                '_id' => 'fallback',
                '_source' => [
                    '_embeddings' => [
                        'title' => [
                            'vector' => ['invalid'],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame(['fallback'], array_column($mmr->diversify($hitsWithoutVectors, $seedDocs, 'title', 1), '_id'));

        $connection = new class implements ElasticsearchConnection
        {
            public array $requests = [];

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                $this->requests[] = [
                    'method' => $request->getMethod(),
                    'path' => $request->getUri()->getPath(),
                    'query' => rawurldecode($request->getUri()->getQuery()),
                    'body' => json_decode((string) $request->getBody(), true),
                ];

                return $request->response(new PsrResponse(200, ['Content-Type' => 'application/json'], '{"ok":true}'));
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                return new FulfilledPromise($this($request));
            }

            public function driver(): SearchEngine
            {
                return new Opensearch;
            }

            public function isServerless(): bool
            {
                return false;
            }
        };
        $pit = new PointInTimeRequests($connection);

        $pit->open('products', '2m');
        $pit->close('pit-id');

        $this->assertSame('POST', $connection->requests[0]['method']);
        $this->assertSame('/products/_search/point_in_time', $connection->requests[0]['path']);
        $this->assertSame('keep_alive=2m', $connection->requests[0]['query']);
        $this->assertSame('DELETE', $connection->requests[1]['method']);
        $this->assertSame('/_search/point_in_time', $connection->requests[1]['path']);
        $this->assertSame(['pit_id' => ['pit-id']], $connection->requests[1]['body']);
    }

    /**
     * @test
     */
    public function semantic_field_rejects_invalid_dimensions_after_elasticsearch_hit(): void
    {
        $this->assertUtilitySearchHit();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Dimensions must be one of: 128, 256, 384, 512, 1024, 1536, 2048, 3072');

        (new NewSemanticField('title'))->accuracy(1, 42);
    }

    /**
     * @test
     */
    public function semantic_field_rejects_invalid_accuracy_after_elasticsearch_hit(): void
    {
        $this->assertUtilitySearchHit();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Accuracy level must be between 1 and 7');

        (new NewSemanticField('title'))->accuracy(8, 128);
    }

    /**
     * @test
     */
    public function api_trait_guard_paths_are_backed_by_elasticsearch_hits(): void
    {
        $this->assertUtilitySearchHit();

        $wrapper = new class
        {
            use UsesApis;

            public function embedding(?string $name = null): mixed
            {
                return $this->getApi($name);
            }

            public function rerank(?string $name = null): mixed
            {
                return $this->getRerankApi($name);
            }

            public function has(?string $name = null): bool
            {
                return $this->hasApi($name);
            }
        };

        $this->assertSame($wrapper, $wrapper->apis([
            'embedding' => $this->embeddingApi,
            'rerank' => $this->rerankApi,
            'invalid' => (object) [],
        ]));
        $this->assertNull($wrapper->embedding());
        $this->assertNull($wrapper->embedding('invalid'));
        $this->assertSame($this->embeddingApi, $wrapper->embedding('embedding'));
        $this->assertNull($wrapper->rerank());
        $this->assertNull($wrapper->rerank(''));
        $this->assertSame($this->rerankApi, $wrapper->rerank('rerank'));
        $this->assertTrue($wrapper->has());
        $this->assertTrue($wrapper->has('invalid'));
        $this->assertFalse($wrapper->has('missing'));
    }

    /**
     * @test
     */
    public function fake_api_tracking_paths_are_backed_by_elasticsearch_hits(): void
    {
        $this->assertUtilitySearchHit();

        $this->embeddingApi->embed('accounting guide', 8);
        $this->embeddingApi->batchEmbed([
            ['text' => 'sales handbook', 'dims' => 8],
        ]);

        $this->assertCount(1, $this->embeddingApi->getEmbedCalls());
        $this->assertCount(1, $this->embeddingApi->getBatchEmbedCalls());
        $this->embeddingApi->assertBatchEmbedWasCalledWithCount(1);

        $scores = $this->rerankApi->rerank(['accounting guide', 'sales handbook'], 'accounting', null);

        $this->assertCount(2, $scores);
        $this->assertCount(1, $this->rerankApi->getRerankCalls());

        $imageSource = 'https://example.com/basketball-orange.jpg';

        $this->clipApi->batchEmbed([
            ['text' => $imageSource, 'dims' => 8],
            ['text' => 'plain text', 'dims' => 8],
        ]);

        $this->clipApi->assertBatchContainedMix(1, 1);
        $this->clipApi->assertImageSourceWasEmbedded($imageSource);
        $this->assertCount(1, $this->clipApi->getImageEmbedCalls());
        $this->assertCount(1, $this->clipApi->getTextEmbedCalls());
        $this->assertCount(1, $this->clipApi->getMixedBatchCalls());
    }

    /**
     * @test
     */
    public function fake_embedding_missing_text_assertion_fails_after_elasticsearch_hit(): void
    {
        $this->assertUtilitySearchHit();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('embed() was never called with text: "missing"');

        $this->embeddingApi->assertEmbedWasCalledWith('missing');
    }

    /**
     * @test
     */
    public function fake_embedding_missing_batch_count_assertion_fails_after_elasticsearch_hit(): void
    {
        $this->assertUtilitySearchHit();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('batchEmbed() was never called with 3 items');

        $this->embeddingApi->assertBatchEmbedWasCalledWithCount(3);
    }

    /**
     * @test
     */
    public function fake_rerank_missing_query_assertion_fails_after_elasticsearch_hit(): void
    {
        $this->assertUtilitySearchHit();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('rerank() was never called with query: "missing"');

        $this->rerankApi->assertRerankWasCalledWith('missing');
    }

    /**
     * @test
     */
    public function fake_clip_missing_image_source_assertion_fails_after_elasticsearch_hit(): void
    {
        $this->assertUtilitySearchHit();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage("Image from source 'missing.jpg' was never embedded");

        $this->clipApi->assertImageSourceWasEmbedded('missing.jpg');
    }

    /**
     * @test
     */
    public function field_type_helper_paths_are_backed_by_elasticsearch_hits(): void
    {
        $this->assertUtilitySearchHit();

        $facetDefaults = new class
        {
            use HasFacets;
        };

        $facetDefaults->aggregation(new Aggs, '10');

        $this->assertFalse($facetDefaults->isFacetable());
        $this->assertNull($facetDefaults->facets([]));
        $this->assertFalse($facetDefaults->isFacetSearchable());

        $html = new HtmlField('body');
        $htmlQueries = $html->queries('hello');

        $this->assertCount(1, $htmlQueries);
        $this->assertSame('body', $html->name());

        $id = new IdField('id');

        $this->assertSame('id', $id->filterableName());
        $this->assertSame([false, 'The field id mapped as identifier must be an integer'], $id->validate('id', 'abc'));
        $this->assertSame([true, ''], $id->validate('id', 123));

        $name = new NameField('name');

        $this->assertSame(['name', 'name.name_text'], $name->names());
        $this->assertCount(3, $name->queries('Nico'));
    }

    /**
     * @test
     */
    public function raw_query_and_point_in_time_helpers_are_backed_by_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Raw query coverage'], _id: 'matching'),
            ]);

        $rawQuery = (new RawQuery($this->elasticsearchConnection, $indexName, [
            'query' => ['match_all' => (object) []],
            'size' => 1,
        ]))->chunk(1);

        $ids = [];
        $rawQuery->each(function (Hit $hit) use (&$ids): void {
            $ids[] = $hit->_id;
        });

        $this->assertSame(['matching'], $ids);
        $this->assertEquals([
            ['index' => $indexName],
            ['query' => ['match_all' => (object) []], 'size' => 1],
        ], $rawQuery->toMultiSearch());
        $this->assertSame(1, $rawQuery->multisearchResCount());
        $this->assertSame(['ok' => true], $rawQuery->formatResponses(['ok' => true]));

        $this->assertSame([['_shard_doc' => 'asc']], PitSortPlanner::plan([], false));
        $this->assertSame([['_id' => 'asc']], PitSortPlanner::plan(['_score'], true));
        $this->assertSame(['name'], PitSortPlanner::plan(['name'], false, hasCollapse: true));
        $this->assertSame([['rank' => ['order' => 'asc']], ['_shard_doc' => 'asc']], PitSortPlanner::plan([['rank' => ['order' => 'asc']]], false));
        $this->assertSame([['rank' => ['order' => 'asc']], ['_id' => 'asc']], PitSortPlanner::plan([['rank' => ['order' => 'asc']], ['_id' => 'asc']], true));

        $openSearchPit = HttpElasticsearchResponse::fromPsrResponse(new PsrResponse(200, [], '{"pit_id":"open-pit"}'));
        $elasticPit = HttpElasticsearchResponse::fromPsrResponse(new PsrResponse(200, [], '{"id":"elastic-pit"}'));
        $nestedPit = HttpElasticsearchResponse::fromPsrResponse(new PsrResponse(200, [], '{"pit":{"id":"nested-pit"}}'));
        $emptyPit = HttpElasticsearchResponse::fromPsrResponse(new PsrResponse(200, [], '{}'));

        $this->assertSame('open-pit', PointInTimeIterator::pitIdFromOpenResponse($openSearchPit, true));
        $this->assertSame('elastic-pit', PointInTimeIterator::pitIdFromOpenResponse($elasticPit, false));
        $this->assertSame('nested-pit', PointInTimeIterator::updatedPitIdFromSearchResponse($nestedPit));
        $this->assertNull(PointInTimeIterator::updatedPitIdFromSearchResponse($emptyPit));
    }

    /**
     * @test
     */
    public function explain_and_stats_api_wrappers_use_elasticsearch(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->add(new Document(['title' => 'API wrapper coverage'], _id: 'matching'));

        $wrapper = new class($this->elasticsearchConnection)
        {
            use Explain;
            use Stats;

            public function __construct(ElasticsearchConnection $connection)
            {
                $this->setElasticsearchConnection($connection);
            }

            public function explain(string $index, array $query, string $id): ElasticsearchResponse
            {
                return $this->explainAPICall($index, $query, $id);
            }

            public function stats(string $index): ElasticsearchResponse
            {
                return $this->statsAPICall($index);
            }
        };

        $explain = $wrapper->explain($indexName, ['match_all' => (object) []], 'matching');
        $stats = $wrapper->stats($indexName);

        $this->assertTrue($explain->json('matched'));
        $this->assertStringStartsWith($indexName, (string) array_key_first($stats->json('indices')));
    }

    /**
     * @test
     */
    public function update_api_wrapper_error_path_is_backed_by_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->add(new Document(['title' => 'API update coverage'], _id: 'matching'));

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->fields(['title'])
            ->queryString('update')
            ->hits();

        $this->assertSame(['matching'], array_map(fn ($hit): string => $hit->_id, $hits));

        $wrapper = new class($this->elasticsearchConnection)
        {
            use UpdateApi;

            public function __construct(ElasticsearchConnection $connection)
            {
                $this->setElasticsearchConnection($connection);
            }

            public function update(string $index, string $id, array $data): ElasticsearchResponse
            {
                return $this->updateAPICall($index, $id, $data);
            }
        };

        $this->expectException(ElasticsearchException::class);

        $wrapper->update($indexName, 'matching', ['doc' => ['title' => 'Updated']]);
    }

    private function assertUtilitySearchHit(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Utility coverage'], _id: 'matching'),
            ]);

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->fields(['title'])
            ->queryString('Utility')
            ->hits();

        $this->assertSame(['matching'], array_map(fn ($hit): string => $hit->_id, $hits));
    }
}
