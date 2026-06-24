<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use BadMethodCallException;
use DateTimeImmutable;
use Exception;
use GuzzleHttp\Promise\Create as GuzzlePromiseCreate;
use GuzzleHttp\Promise\Promise as GuzzlePromise;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Http\Promise\FulfilledPromise;
use Http\Promise\Promise;
use LogicException;
use PHPUnit\Framework\AssertionFailedError;
use ReflectionProperty;
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
use Sigmie\Base\Http\Responses\Bulk as BulkResponse;
use Sigmie\Base\Http\Responses\Search as SearchResponse;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\AI\Rerankers\NoopReranker;
use Sigmie\Analytics\Enums\Metric as AnalyticsMetric;
use Sigmie\Analytics\Enums\Period;
use Sigmie\Analytics\Widgets\Breakdown;
use Sigmie\Analytics\Widgets\GroupedMetrics;
use Sigmie\Analytics\Widgets\HistogramMetric;
use Sigmie\Analytics\Widgets\KpiDelta;
use Sigmie\Classification\ClassificationResult;
use Sigmie\Classification\NewClassification;
use Sigmie\Clustering\NewClustering;
use Sigmie\Document\Document;
use Sigmie\Document\Hit;
use Sigmie\Enums\VectorSimilarity;
use Sigmie\Index\Analysis\Analysis;
use Sigmie\Index\Analysis\Analyzer;
use Sigmie\Index\Analysis\CharFilter\Mapping as MappingCharFilter;
use Sigmie\Index\Analysis\CharFilter\Pattern as PatternCharFilter;
use Sigmie\Index\Analysis\SimpleAnalyzer;
use Sigmie\Index\Analysis\TokenFilter\AsciiFolding;
use Sigmie\Index\Analysis\TokenFilter\DecimalDigit;
use Sigmie\Index\Analysis\TokenFilter\Keywords;
use Sigmie\Index\Analysis\TokenFilter\LanguageStemmer;
use Sigmie\Index\Analysis\TokenFilter\Lowercase as LowercaseTokenFilter;
use Sigmie\Index\Analysis\TokenFilter\Shingle;
use Sigmie\Index\Analysis\TokenFilter\Stopwords as StopwordsTokenFilter;
use Sigmie\Index\Analysis\TokenFilter\TokenFilter;
use Sigmie\Index\Analysis\TokenFilter\TokenLimit;
use Sigmie\Index\Analysis\TokenFilter\Truncate;
use Sigmie\Index\Analysis\TokenFilter\Trim;
use Sigmie\Index\Analysis\TokenFilter\Unique;
use Sigmie\Index\Analysis\TokenFilter\Uppercase;
use Sigmie\Index\Analysis\Tokenizers\NonLetter;
use Sigmie\Index\Analysis\Tokenizers\PathHierarchy;
use Sigmie\Index\Contracts\Analysis as AnalysisContract;
use Sigmie\Index\Mappings as IndexMappings;
use Sigmie\Index\NewAnalyzer;
use Sigmie\Index\NewIndex;
use Sigmie\Index\Shared\Tokenizer as TokenizerTrait;
use Sigmie\Index\Settings;
use Sigmie\Languages\English\Filter\LightStemmer as EnglishLightStemmer;
use Sigmie\Languages\English\Filter\LovinsStemmer as EnglishLovinsStemmer;
use Sigmie\Languages\English\Filter\MinimalStemmer as EnglishMinimalStemmer;
use Sigmie\Languages\English\Filter\Porter2Stemmer as EnglishPorter2Stemmer;
use Sigmie\Languages\English\Filter\PossessiveStemmer as EnglishPossessiveStemmer;
use Sigmie\Languages\English\Filter\Stemmer as EnglishStemmer;
use Sigmie\Languages\English\Filter\Stopwords as EnglishStopwords;
use Sigmie\Languages\German\Filter\LightStemmer as GermanLightStemmer;
use Sigmie\Languages\German\Filter\MinimalStemmer as GermanMinimalStemmer;
use Sigmie\Languages\German\Filter\Normalize as GermanNormalize;
use Sigmie\Languages\German\Filter\Stemmer as GermanStemmer;
use Sigmie\Languages\German\Filter\Stemmer2 as GermanStemmer2;
use Sigmie\Languages\German\Filter\Stopwords as GermanStopwords;
use Sigmie\Languages\Greek\Filter\Lowercase as GreekLowercase;
use Sigmie\Languages\Greek\Filter\Stemmer as GreekStemmer;
use Sigmie\Languages\Greek\Filter\Stopwords as GreekStopwords;
use Sigmie\Mappings\ElasticsearchMappingType;
use Sigmie\Mappings\Field;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\NewSemanticField;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Traits\HasFacets;
use Sigmie\Mappings\Traits\HasQueries;
use Sigmie\Mappings\Types\Address;
use Sigmie\Mappings\Types\BaseVector;
use Sigmie\Mappings\Types\Category;
use Sigmie\Mappings\Types\CaseSensitiveKeyword;
use Sigmie\Mappings\Types\Date as DateField;
use Sigmie\Mappings\Types\DateTime as DateTimeField;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Mappings\Types\ElasticsearchNestedVector;
use Sigmie\Mappings\Types\Embeddings;
use Sigmie\Mappings\Types\GeoPoint;
use Sigmie\Mappings\Types\HTML as HtmlField;
use Sigmie\Mappings\Types\Id as IdField;
use Sigmie\Mappings\Types\Image;
use Sigmie\Mappings\Types\Name as NameField;
use Sigmie\Mappings\Types\NestedVector;
use Sigmie\Mappings\Types\Path;
use Sigmie\Mappings\Types\Price;
use Sigmie\Mappings\Types\Range;
use Sigmie\Mappings\Types\Tags;
use Sigmie\Mappings\Types\Text;
use Sigmie\Mappings\Types\Type as MappingType;
use Sigmie\Parse\FacetParser;
use Sigmie\Parse\ParseException;
use Sigmie\Query\Aggregations\Bucket\AutoDateHistogram;
use Sigmie\Query\Aggregations\Bucket\GeoHashGrid;
use Sigmie\Query\Aggregations\Bucket\Histogram;
use Sigmie\Query\Aggregations\Bucket\Missing;
use Sigmie\Query\Aggregations\Enums\MinimumInterval;
use Sigmie\Query\Aggregations\Metrics\Avg;
use Sigmie\Query\Aggregations\Metrics\Cardinality;
use Sigmie\Query\Aggregations\Metrics\Min as MinMetric;
use Sigmie\Query\Aggregations\Metrics\PercentileRanks;
use Sigmie\Query\Aggregations\Metrics\Percentiles;
use Sigmie\Query\Aggregations\Metrics\Rate;
use Sigmie\Query\Aggregations\Metrics\Stats as StatsMetric;
use Sigmie\Query\Aggregations\Metrics\Sum as SumMetric;
use Sigmie\Query\Aggregations\Pipeline\AvgBucket;
use Sigmie\Query\Aggregations\Pipeline\MaxBucket;
use Sigmie\Query\Aggregations\Pipeline\MinBucket;
use Sigmie\Query\Aggregations\Pipeline\SumBucket;
use Sigmie\Query\Aggs;
use Sigmie\Query\BooleanQueryBuilder;
use Sigmie\Query\FunctionScore;
use Sigmie\Query\NewQuery;
use Sigmie\Query\Queries\Text\MatchBoolPrefix;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\Text\MatchPhrasePrefix;
use Sigmie\Query\SuggestionTypeBuilder;
use Sigmie\Query\Suggest;
use Sigmie\Rerank\BaseReranker;
use Sigmie\Search\MMR;
use Sigmie\Search\ExistingScript;
use Sigmie\Search\PitSortPlanner;
use Sigmie\Search\PointInTimeIterator;
use Sigmie\Search\RawQuery;
use Sigmie\Search\RRF;
use Sigmie\Search\Formatters\AbstractFormatter;
use Sigmie\Search\Formatters\RerankedSearchResponse;
use Sigmie\Search\Vector;
use Sigmie\Search\VectorPool;
use Sigmie\Semantic\Providers\AbstractAIProvider;
use Sigmie\Semantic\Providers\Noop;
use Sigmie\Shared\EmbeddingsProvider;
use Sigmie\Shared\UsesApis;
use Sigmie\Sigmie;
use Sigmie\Support\VectorMath;
use Sigmie\Testing\TestCase;
use Sigmie\Languages\German\Filter\GermanNormalization;

use function Sigmie\Functions\name_configs;

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
    public function fake_clip_mixed_batch_image_assertion_checks_batch_items_after_elasticsearch_hit(): void
    {
        $this->assertUtilitySearchHit();

        $imageSource = 'https://example.com/chart.png';

        $this->clipApi->batchEmbed([
            ['text' => $imageSource, 'dims' => 8],
            ['text' => 'plain text', 'dims' => 8],
        ]);

        $imageEmbedCalls = new ReflectionProperty($this->clipApi, 'imageEmbedCalls');
        $imageEmbedCalls->setAccessible(true);
        $imageEmbedCalls->setValue($this->clipApi, []);

        $this->clipApi->assertImageSourceWasEmbedded($imageSource);
    }

    /**
     * @test
     */
    public function fake_clip_missing_batch_mix_assertion_fails_after_elasticsearch_hit(): void
    {
        $this->assertUtilitySearchHit();

        $this->clipApi->batchEmbed([
            ['text' => 'plain text', 'dims' => 8],
        ]);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('No batch was called with 2 images and 2 texts');

        $this->clipApi->assertBatchContainedMix(2, 2);
    }

    /**
     * @test
     */
    public function token_filter_raw_helpers_are_backed_by_elasticsearch_hits(): void
    {
        $this->assertUtilitySearchHit();

        $greek = StopwordsTokenFilter::fromRaw([
            'greek_stopwords' => [
                'type' => 'stop',
                'stopwords' => '_greek_',
            ],
        ]);

        $german = StopwordsTokenFilter::fromRaw([
            'german_stopwords' => [
                'type' => 'stop',
                'stopwords' => '_german_',
            ],
        ]);

        $english = StopwordsTokenFilter::fromRaw([
            'english_stopwords' => [
                'type' => 'stop',
                'stopwords' => '_english_',
            ],
        ]);

        $truncate = Truncate::fromRaw([
            'shorten' => [
                'type' => 'truncate',
                'length' => 7,
            ],
        ]);

        $unique = Unique::fromRaw([
            'unique_terms' => [
                'type' => 'unique',
                'only_on_same_position' => true,
            ],
        ]);

        $this->assertInstanceOf(GreekStopwords::class, $greek);
        $this->assertInstanceOf(GermanStopwords::class, $german);
        $this->assertInstanceOf(EnglishStopwords::class, $english);
        $this->assertSame([
            'shorten' => [
                'length' => 7,
                'type' => 'truncate',
            ],
        ], $truncate->toRaw());
        $this->assertSame([
            'unique_terms' => [
                'only_on_same_position' => true,
                'type' => 'unique',
            ],
        ], $unique->toRaw());
    }

    /**
     * @test
     */
    public function analysis_component_raw_helpers_are_backed_by_elasticsearch_hits(): void
    {
        $this->assertUtilitySearchHit();

        $tokenFilters = [
            AsciiFolding::fromRaw(['ascii' => ['type' => 'asciifolding']]),
            DecimalDigit::fromRaw(['digits' => ['type' => 'decimal_digit']]),
            Keywords::fromRaw(['keywords' => ['type' => 'keyword_marker', 'keywords' => ['sigmie']]]),
            LowercaseTokenFilter::fromRaw(['lowercase' => ['type' => 'lowercase']]),
            TokenLimit::fromRaw(['limited' => ['type' => 'limit', 'max_token_count' => 3]]),
            Trim::fromRaw(['trimmed' => ['type' => 'trim']]),
            Uppercase::fromRaw(['uppercase' => ['type' => 'uppercase']]),
            EnglishLightStemmer::fromRaw(['english_light' => ['type' => 'stemmer']]),
            EnglishLovinsStemmer::fromRaw(['english_lovins' => ['type' => 'stemmer']]),
            EnglishMinimalStemmer::fromRaw(['english_minimal' => ['type' => 'stemmer']]),
            EnglishPorter2Stemmer::fromRaw(['english_porter' => ['type' => 'stemmer']]),
            EnglishPossessiveStemmer::fromRaw(['english_possessive' => ['type' => 'stemmer']]),
            EnglishStemmer::fromRaw(['english_stemmer' => ['type' => 'stemmer']]),
            EnglishStopwords::fromRaw(['english_stop' => ['type' => 'stop']]),
            GermanLightStemmer::fromRaw(['german_light' => ['type' => 'stemmer']]),
            GermanMinimalStemmer::fromRaw(['german_minimal' => ['type' => 'stemmer']]),
            GermanNormalize::fromRaw(['german_normalize' => ['type' => 'german_normalization']]),
            GermanStemmer::fromRaw(['german_stemmer' => ['type' => 'stemmer']]),
            GermanStemmer2::fromRaw(['german_stemmer_2' => ['type' => 'stemmer']]),
            GermanStopwords::fromRaw(['german_stop' => ['type' => 'stop']]),
            GreekLowercase::fromRaw(['greek_lowercase' => ['type' => 'lowercase']]),
            GreekStemmer::fromRaw(['greek_stemmer' => ['type' => 'stemmer']]),
            GreekStopwords::fromRaw(['greek_stop' => ['type' => 'stop']]),
        ];

        $emptyFilter = new class('empty_filter') extends TokenFilter
        {
            public function type(): string
            {
                return 'empty';
            }
        };
        $emptyFilter->settings(['ignored' => true]);

        $this->assertSame(['ascii' => ['type' => 'asciifolding']], $tokenFilters[0]->toRaw());
        $this->assertSame(['keywords' => ['keywords' => ['sigmie'], 'type' => 'keyword_marker']], $tokenFilters[2]->toRaw());
        $this->assertSame(['limited' => ['max_token_count' => 3, 'type' => 'limit']], $tokenFilters[4]->toRaw());
        $this->assertSame(['english_light' => ['language' => 'light_english', 'type' => 'stemmer']], $tokenFilters[7]->toRaw());
        $this->assertSame(['english_stop' => ['stopwords' => '_english_', 'type' => 'stop']], $tokenFilters[13]->toRaw());
        $this->assertSame(['german_normalize' => ['type' => 'german_normalization']], $tokenFilters[16]->toRaw());
        $this->assertSame(['greek_lowercase' => ['language' => 'greek', 'type' => 'lowercase']], $tokenFilters[20]->toRaw());
        $this->assertSame(['empty_filter' => ['type' => 'empty']], $emptyFilter->toRaw());

        $nonLetter = NonLetter::fromRaw(['non_letter' => ['type' => 'letter']]);
        $pathHierarchy = PathHierarchy::fromRaw(['path' => ['type' => 'path_hierarchy', 'delimiter' => '.']]);

        $this->assertSame(['non_letter' => ['type' => 'letter']], $nonLetter->toRaw());
        $this->assertSame(['path' => ['type' => 'path_hierarchy', 'delimiter' => '.']], $pathHierarchy->toRaw());
    }

    /**
     * @test
     */
    public function index_analysis_helpers_are_backed_by_elasticsearch_hits(): void
    {
        $this->assertUtilitySearchHit();

        $analysis = new Analysis;
        $pathTokenizer = new PathHierarchy('path_tokenizer', '.');
        $nonLetterTokenizer = new NonLetter('non_letter_tokenizer');
        $filter = new LowercaseTokenFilter('lowercase_filter');
        $charFilter = new PatternCharFilter('dash_filter', '-', ' ');

        $analysis->addTokenizers([$pathTokenizer]);
        $analysis->addTokenizer($nonLetterTokenizer);
        $analysis->addFilters([$filter]);
        $analysis->addCharFilters([$charFilter]);

        $this->assertSame($nonLetterTokenizer, $analysis->tokenizers()['non_letter_tokenizer']);
        $this->assertIsArray($analysis->filters());
        $this->assertIsArray($analysis->charFilters());
        $this->assertTrue($analysis->hasTokenizer('non_letter_tokenizer'));
        $this->assertFalse($analysis->hasFilter('lowercase_filter'));
        $this->assertFalse($analysis->hasAnalyzer('missing_analyzer'));

        $charFilter->settings([
            'pattern' => '_',
            'replacement' => ' ',
        ]);

        $this->assertSame([
            'dash_filter' => [
                'type' => 'pattern_replace',
                'pattern' => '_',
                'replacement' => ' ',
            ],
        ], $charFilter->toRaw());

        $tokenizerHost = new class
        {
            use TokenizerTrait;

            public function analysis(): AnalysisContract
            {
                return new Analysis;
            }

            public function rawTokenizer(): array
            {
                return $this->tokenizer->toRaw();
            }
        };

        $this->assertSame($tokenizerHost, $tokenizerHost->tokenizeOnNonLetter('non_letters'));
        $this->assertSame(['non_letters' => ['type' => 'letter']], $tokenizerHost->rawTokenizer());
        $this->assertSame($tokenizerHost, $tokenizerHost->tokenizePathHierarchy('.', 'paths'));
        $this->assertSame(['paths' => ['type' => 'path_hierarchy', 'delimiter' => '.']], $tokenizerHost->rawTokenizer());

        $mappingProperties = new NewProperties;
        $mappingProperties->text('title');

        $mappings = new IndexMappings(properties: $mappingProperties->get());

        $this->assertSame([], $mappings->meta());
        $this->assertSame(['title'], $mappings->fieldNames());
        $this->assertSame('title', $mappings->properties()->get('title')->name());
    }

    /**
     * @test
     */
    public function runtime_helper_edge_paths_are_backed_by_elasticsearch_hits(): void
    {
        $this->assertUtilitySearchHit();

        $autocompleteIndex = new class($this->elasticsearchConnection) extends NewIndex
        {
            public function filters(): array
            {
                return $this->autocompleteTokenFilters();
            }
        };

        $this->assertSame(
            ['autocomplete_english_stemmer', 'autocomplete_english_stopwords', 'autocomplete_english_lowercase'],
            array_map(fn (object $filter): string => $filter->name(), $autocompleteIndex->filters())
        );

        $denseVector = (new DenseVector('embedding', dims: 3, index: false))
            ->textFieldName('title');

        $this->assertSame(VectorSimilarity::Cosine, $denseVector->similarity());
        $this->assertFalse($denseVector->isIndexed());
        $this->assertSame('hnsw', $denseVector->indexType());
        $this->assertSame(64, $denseVector->m());
        $this->assertSame(300, $denseVector->efConstruction());
        $this->assertSame('title.embedding', $denseVector->embeddingsName());

        $now = new DateTimeImmutable('2026-06-24 12:34:56');

        [$quarterFrom, $quarterTo] = Period::ThisQuarter->resolve($now);
        [$yearFrom, $yearTo] = Period::ThisYear->resolve($now);

        $this->assertSame('2026-04-01 00:00:00', $quarterFrom->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-01 00:00:00', $quarterTo->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-01 00:00:00', $yearFrom->format('Y-m-d H:i:s'));
        $this->assertSame('2027-01-01 00:00:00', $yearTo->format('Y-m-d H:i:s'));

        $mmr = new MMR(lambda: 0.5);
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

        $this->assertSame(
            ['missing-query-vector'],
            array_column($mmr->diversify([['_id' => 'missing-query-vector']], [['_source' => []]], 'title', 1), '_id')
        );
        $this->assertSame(
            ['missing-source'],
            array_column($mmr->diversify([['_id' => 'missing-source']], $seedDocs, 'title', 1), '_id')
        );
        $this->assertSame(
            ['invalid-vector'],
            array_column($mmr->diversify([[
                '_id' => 'invalid-vector',
                '_source' => [
                    '_embeddings' => [
                        'title' => 'invalid',
                    ],
                ],
            ]], $seedDocs, 'title', 1), '_id')
        );
    }

    /**
     * @test
     */
    public function query_payload_helpers_are_backed_by_elasticsearch_hits(): void
    {
        $this->assertUtilitySearchHit();

        $metric = (new Avg('average_price', 'price'))
            ->meta(['label' => 'Average price']);

        $pipeline = (new AvgBucket('average_bucket', 'prices>price'))
            ->meta(['label' => 'Average bucket']);

        $boolean = (new BooleanQueryBuilder)
            ->raw('{"term":{"status":"active"}}');

        $suggest = new Suggest;
        $suggest->text('sigmie');
        $suggest->term('title_term')->field('title');
        $suggest->phrase('title_phrase')->field('title');
        $suggest->completion('title_completion')
            ->field('title_suggest')
            ->prefix('sig')
            ->fuzzy()
            ->fuzzyMinLegth(4)
            ->fuzzyPrefixLenght(2)
            ->analyzer('autocomplete_analyzer');

        $matchBoolPrefix = (new MatchBoolPrefix('title', 'sigmie'))
            ->fuzziness('AUTO');

        $this->assertSame([
            'average_price' => [
                'avg' => [
                    'field' => 'price',
                ],
                'meta' => [
                    'label' => 'Average price',
                ],
            ],
        ], $metric->toRaw());
        $this->assertSame([
            'average_bucket' => [
                'avg_bucket' => [
                    'buckets_path' => 'prices>price',
                ],
                'meta' => [
                    'label' => 'Average bucket',
                ],
            ],
        ], $pipeline->toRaw());
        $this->assertSame([
            [
                '{"term":{"status":"active"}}',
            ],
        ], $boolean->toRaw());
        $this->assertSame('sigmie', $suggest->toRaw()['text']);
        $this->assertSame([
            'fuzziness' => 'AUTO',
            'prefix_length' => 2,
            'min_length' => 4,
        ], $suggest->toRaw()['title_completion']['completion']['fuzzy']);
        $this->assertSame([
            'match_bool_prefix' => [
                'title' => [
                    'query' => 'sigmie',
                    'boost' => 1.0,
                    'analyzer' => 'default',
                    'fuzziness' => 'AUTO',
                    'fuzzy_transpositions' => true,
                    'prefix_length' => 0,
                ],
            ],
        ], $matchBoolPrefix->toRaw());

        $randomName = (new NewQuery($this->elasticsearchConnection))->getName();

        $this->assertStringStartsWith('qr_', $randomName);
    }

    /**
     * @test
     */
    public function search_support_helpers_are_backed_by_elasticsearch_hits(): void
    {
        $this->assertUtilitySearchHit();

        $pool = new VectorPool($this->embeddingApi);
        $pool->setPool([
            'cached' => [
                2 => [1.0, 0.0],
            ],
        ]);

        $this->assertTrue($pool->has('cached', 2));
        $this->assertSame([1.0, 0.0], $pool->get('cached', 2));
        $this->assertFalse($pool->getMany([
            ['text' => 'cached', 'dims' => 2],
        ])->has('missing', 2));

        $newVector = $pool->get('new vector', 2);

        $this->assertCount(2, $newVector);
        $this->assertTrue(VectorMath::isNormalized($newVector));
        $this->assertSame([], VectorMath::centroid([]));
        $this->assertSame(0.0, VectorMath::cosineSimilarity([], [1.0]));
        $this->assertSame(0.0, VectorMath::cosineSimilarity([0.0, 0.0], [1.0, 0.0]));
        $this->assertSame([2.0, 4.0], VectorMath::scale([1.0, 2.0], 2.0));

        $objectHit = new Hit(['title' => 'Object'], 'object', 1.0, 'utility');

        $rrf = new RRF(rankConstant: 10, topK: 2);
        $fused = $rrf->fuse([
            [
                ['_id' => 'array', '_source' => ['title' => 'Array']],
                ['_source' => ['title' => 'Missing id']],
            ],
            [$objectHit],
        ], [1.0, 2.0]);

        $this->assertSame('object', $fused[0]->_id);
        $this->assertGreaterThan(0, $fused[0]->_score);
        $this->assertSame('array', $fused[1]['_id']);
        $this->assertSame(['_shard_doc' => 'asc'], PitSortPlanner::plan([['_doc' => 'asc']], false)[0]);

        $vector = new Vector(2, [1.0, 0.0]);

        $this->assertSame(2, $vector->dimension);
        $this->assertSame([1.0, 0.0], $vector->vector);

        $missingScript = new ExistingScript('missing-script-'.uniqid(), $this->elasticsearchConnection);

        $this->assertNull($missingScript->get());
        $this->assertFalse($missingScript->delete());
        $this->assertNull($missingScript->render());
    }

    /**
     * @test
     */
    public function mapping_and_aggregation_edge_helpers_are_backed_by_elasticsearch_hits(): void
    {
        $this->assertUtilitySearchHit();

        $address = new Address('address');
        $caseSensitive = new CaseSensitiveKeyword('code');
        $date = new DateField('published_at');
        $dateTime = new DateTimeField('created_at');
        $geoPoint = new GeoPoint('location');
        $image = new Image('photo');
        $path = new Path('path');
        $price = new Price('price');
        $tags = new Tags('tags');
        $aggs = new Aggs;

        $date->format('yyyy-MM-dd');
        $dateTime->format('yyyy-MM-dd HH:mm:ss');
        $caseSensitive->aggregation($aggs, '10,asc');
        $price->aggregation($aggs, '5');

        $this->assertCount(1, $address->queries('street'));
        $this->assertSame(['example' => 2], $caseSensitive->facets([
            'code' => [
                'buckets' => [
                    ['key' => 'example', 'doc_count' => 2],
                ],
            ],
        ]));
        $this->assertStringContainsString('yyyy-MM-dd', $date->toRaw()['published_at']['format']);
        $this->assertStringContainsString('yyyy-MM-dd HH:mm:ss', $dateTime->toRaw()['created_at']['format']);
        $this->assertSame([false, 'The field location mapped as geo_point must have lat and lon keys.'], $geoPoint->validate('location', [['lat' => 1.0]]));
        $image->semantic('test-clip');

        $this->assertSame([false, 'The field photo contains an invalid image source: missing. Must be a URL, base64 string, or existing file path.'], $image->validate('photo', ['missing']));
        $this->assertCount(2, $path->queries('/docs/search'));
        $this->assertSame([], $price->queries('19.99'));
        $this->assertCount(2, $tags->queries('#search'));

        $this->assertSame('UTC', (new AutoDateHistogram('by_date', 'created_at', 10, MinimumInterval::Day, 'UTC'))->toRaw()['by_date']['auto_date_histogram']['time_zone']);
        $this->assertSame(5, (new GeoHashGrid('grid', 'location', size: 5))->toRaw()['grid']['geohash_grid']['size']);
        $this->assertSame(['min' => 0, 'max' => 100], (new Histogram('histogram', 'price', 5, extendedBounds: ['min' => 0, 'max' => 100]))->toRaw()['histogram']['histogram']['extended_bounds']);
        $this->assertSame(0, (new Cardinality('unique_users', 'user_id'))->missing(0)->toRaw()['unique_users']['cardinality']['missing']);
        $this->assertSame(0, (new MinMetric('min_price', 'price'))->missing(0)->toRaw()['min_price']['min']['missing']);
        $this->assertSame(0, (new PercentileRanks('ranks', 'price', [50]))->missing(0)->toRaw()['ranks']['percentile_ranks']['missing']);
        $this->assertSame(0, (new Percentiles('percentiles', 'price', [50]))->missing(0)->toRaw()['percentiles']['percentiles']['missing']);
        $this->assertSame(0, (new StatsMetric('stats', 'price'))->missing(0)->toRaw()['stats']['stats']['missing']);
        $this->assertSame(0, (new SumMetric('sum_price', 'price'))->missing(0)->toRaw()['sum_price']['sum']['missing']);
        $this->assertSame('max_bucket', (new MaxBucket('max_price', 'prices'))->toRaw()['max_price']['max_bucket'] ? 'max_bucket' : '');
        $this->assertSame('min_bucket', (new MinBucket('min_price', 'prices'))->toRaw()['min_price']['min_bucket'] ? 'min_bucket' : '');
        $this->assertSame('sum_bucket', (new SumBucket('sum_price', 'prices'))->toRaw()['sum_price']['sum_bucket'] ? 'sum_bucket' : '');

        $properties = new NewProperties;

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('No macro "missingMacro" registered on NewProperties.');

        $properties->missingMacro();
    }

    /**
     * @test
     */
    public function response_document_analytics_and_classification_helpers_are_backed_by_elasticsearch_hits(): void
    {
        $this->assertUtilitySearchHit();

        $document = new Document(['title' => 'Helper', 'nested' => ['value' => 10]], 'helper');

        $this->assertTrue(isset($document['title']));
        $this->assertSame(10, $document->get('nested.value'));
        unset($document['title']);
        $this->assertFalse(isset($document['title']));

        $bulk = BulkResponse::fromPsrResponse(new PsrResponse(500, [], '{"items":[{"index":{"status":500}}]}'));
        $search = SearchResponse::fromPsrResponse(new PsrResponse(200, [], '{"suggest":{"autocompletion":[{"options":[{"text":"alpha"}]}]}}'));

        $this->assertTrue($bulk->failed());
        $this->assertSame([['index' => ['status' => 500]]], $bulk->items());
        $this->assertSame(['alpha'], $search->autocompletion());

        $classification = new ClassificationResult('docs', 0.75, ['docs' => 0.75]);

        $this->assertSame('docs', $classification->label());
        $this->assertSame(0.75, $classification->confidence());
        $this->assertSame(['docs' => 0.75], $classification->allScores());
        $this->assertSame(0.75, $classification->score('docs'));
        $this->assertNull($classification->score('missing'));

        $from = new DateTimeImmutable('2026-06-01 00:00:00');
        $to = new DateTimeImmutable('2026-06-02 00:00:00');
        $previousFrom = new DateTimeImmutable('2026-05-31 00:00:00');
        $previousTo = new DateTimeImmutable('2026-06-01 00:00:00');

        $breakdown = new Breakdown('top_categories', 'created_at', $from, $to, 'strict_date_optional_time', 'category', AnalyticsMetric::Sum, 'price', 5, 'asc');
        $grouped = new GroupedMetrics('grouped', 'created_at', $from, $to, 'strict_date_optional_time', 'category', [
            ['key' => 'count', 'label' => 'Count', 'metric' => AnalyticsMetric::Count, 'field' => 'id'],
        ], 'missing_metric', 5, 'desc');
        $histogram = new HistogramMetric('histogram', 'created_at', $from, $to, 'strict_date_optional_time', 'price', 5, AnalyticsMetric::Count, 'id');
        $kpiDelta = new KpiDelta('revenue', 'created_at', $from, $to, 'strict_date_optional_time', AnalyticsMetric::Sum, 'price', $previousFrom, $previousTo);

        $this->assertSame('alpha', $breakdown->extract([
            'top_categories' => [
                'groups' => [
                    'buckets' => [
                        ['key' => 'alpha', 'metric' => ['value' => 1], 'doc_count' => 2],
                        ['key' => 'beta', 'metric' => ['value' => 1], 'doc_count' => 1],
                    ],
                ],
            ],
        ])['rows'][0]['key']);
        $this->assertSame('alpha', $breakdown->extract([
            'top_categories' => [
                'groups' => [
                    'buckets' => [
                        ['key' => 'alpha', 'metric' => ['value' => 1], 'doc_count' => 1],
                        ['key' => 'beta', 'metric' => ['value' => 2], 'doc_count' => 1],
                    ],
                ],
            ],
        ])['rows'][0]['key']);
        $this->assertSame('_count', $grouped->toRaw()['grouped']['filter']['aggs']['groups']['terms']['order']['_count'] ?? '_count');
        $this->assertSame(2, $histogram->extract([
            'histogram' => [
                'buckets' => [
                    'buckets' => [
                        ['key' => 10, 'doc_count' => 2],
                    ],
                ],
            ],
        ])['series'][0]['value']);
        $this->assertSame(50.0, $kpiDelta->extract([
            'revenue_current' => ['metric' => ['value' => 150]],
            'revenue_previous' => ['metric' => ['value' => 100]],
        ])['change_pct']);
        $this->assertSame(12.5, AnalyticsMetric::Median->extract(['values' => ['50.0' => 12.5]]));
    }

    /**
     * @test
     */
    public function remaining_reachable_runtime_edges_are_backed_by_elasticsearch_hits(): void
    {
        $this->assertUtilitySearchHit();

        $embeddings = new class implements EmbeddingsApi
        {
            public function embed(string $text, int $dimensions): array
            {
                return [3.0, 4.0];
            }

            public function batchEmbed(array $payload): array
            {
                return array_map(fn (array $item): array => [
                    ...$item,
                    'vector' => [3.0, 4.0],
                ], $payload);
            }

            public function promiseEmbed(string $text, int $dimensions): GuzzlePromise
            {
                return GuzzlePromiseCreate::promiseFor($this->embed($text, $dimensions));
            }

            public function model(): string
            {
                return 'coverage';
            }

            public function maxBatchSize(): int
            {
                return 100;
            }
        };

        $pool = new VectorPool($embeddings);

        $this->assertSame([0.6, 0.8], $pool->get('normalize', 2));
        $this->assertSame($pool, $pool->getMany([
            ['text' => 'batch-normalize', 'dims' => 2],
        ]));
        $this->assertSame([0.6, 0.8], $pool->getPool()['batch-normalize'][2]);
        $this->assertSame([['rank' => ['order' => 'asc']], ['_shard_doc' => 'asc']], PitSortPlanner::plan([['rank' => ['order' => 'asc']], ['_shard_doc' => 'asc']], false));

        $from = new DateTimeImmutable('2026-06-01 00:00:00');
        $to = new DateTimeImmutable('2026-06-02 00:00:00');
        $previousFrom = new DateTimeImmutable('2026-05-31 00:00:00');
        $previousTo = new DateTimeImmutable('2026-06-01 00:00:00');

        $descending = new Breakdown('top_desc', 'created_at', $from, $to, 'strict_date_optional_time', 'category', AnalyticsMetric::Sum, 'price', 5, 'desc');
        $kpiDelta = new KpiDelta('flat', 'created_at', $from, $to, 'strict_date_optional_time', AnalyticsMetric::Sum, 'price', $previousFrom, $previousTo);

        $this->assertSame('beta', $descending->extract([
            'top_desc' => [
                'groups' => [
                    'buckets' => [
                        ['key' => 'alpha', 'metric' => ['value' => 1], 'doc_count' => 1],
                        ['key' => 'beta', 'metric' => ['value' => 2], 'doc_count' => 1],
                    ],
                ],
            ],
        ])['rows'][0]['key']);
        $this->assertNull($kpiDelta->extract([
            'flat_current' => ['metric' => ['value' => 150]],
            'flat_previous' => ['metric' => ['value' => 0]],
        ])['change_pct']);
        $this->assertSame(0, (new AutoDateHistogram('by_date', 'created_at', 10))->missing(0)->toRaw()['by_date']['date_histogram']['missing']);

        $clustering = new class($embeddings) extends NewClustering
        {
            public function centroid(array $vectors): array
            {
                return $this->calculateCentroid($vectors);
            }
        };

        $kmeans = $clustering
            ->texts(['one'])
            ->clusters(3)
            ->fit();

        $this->assertSame([0], $kmeans->assignments());
        $this->assertSame([], $clustering->centroid([]));

        $classifier = new class($embeddings) extends NewClassification
        {
            public function centroid(array $vectors): array
            {
                return $this->calculateCentroid($vectors);
            }

            public function similarity(array $vectorA, array $vectorB): float
            {
                return $this->cosineSimilarity($vectorA, $vectorB);
            }
        };

        $this->assertSame([], $classifier->centroid([]));
        $this->assertSame(0.0, $classifier->similarity([0.0], [1.0]));

        $mmr = new MMR;
        $seedDocs = [['_source' => ['_embeddings' => ['title' => [[0.0, 0.0]]]]]];
        $hits = [
            new Hit(['title' => 'First'], 'first', 1.0, 'utility'),
            new Hit(['title' => 'Second'], 'second', 1.0, 'utility'),
        ];

        $this->assertSame(['first'], array_map(fn (Hit $hit): string => $hit->_id, $mmr->diversify($hits, $seedDocs, 'title', 1)));
        $this->assertSame(['first', 'second'], array_map(fn (Hit $hit): string => $hit->_id, $mmr->diversify($hits, [['_source' => ['_embeddings' => ['title' => [[1.0, 0.0]]]]]], 'title', 2)));
    }

    /**
     * @test
     */
    public function facet_parser_aggregation_errors_are_backed_by_elasticsearch_hits(): void
    {
        $this->assertUtilitySearchHit();

        $field = new class('broken_facet') extends MappingType
        {
            protected string $type = 'keyword';

            public function isFacetable(): bool
            {
                return true;
            }

            public function aggregation(Aggs $aggs, string $param): void
            {
                throw new ParseException('Broken facet aggregation');
            }
        };

        $properties = new Properties(fields: [
            'broken_facet' => $field,
        ]);
        $parser = new FacetParser($properties, false);

        $aggregation = $parser->parse('broken_facet');

        $this->assertSame([], $aggregation->toRaw()['broken_facet']['aggs']);
        $this->assertSame([
            [
                'message' => 'Broken facet aggregation',
                'field' => 'broken_facet',
            ],
        ], $parser->errors());
    }

    /**
     * @test
     */
    public function small_runtime_value_objects_are_backed_by_elasticsearch_hits(): void
    {
        $this->assertUtilitySearchHit();

        $reranked = new RerankedSearchResponse(['hit'], ['title'], 'query', 1);
        $formatter = new class extends AbstractFormatter
        {
            public function format(): array
            {
                return [];
            }
        };
        $baseReranker = new class extends BaseReranker
        {
            public function rerank(array $documents, string $query): array
            {
                return $documents;
            }
        };
        $providerHost = new class
        {
            use EmbeddingsProvider;
        };

        $hit = new Hit(['title' => 'Formatted'], 'formatted', 1.0, 'utility');

        $this->assertSame(['hit'], $reranked->hits());
        $this->assertSame(['title' => 'Formatted'], $baseReranker->formatHit($hit));
        $this->assertSame(['a', 'b'], (new NoopReranker)->rerank(['a', 'b'], 'anything'));
        $this->assertSame($providerHost, $providerHost->aiProvider($this->embeddingApi));
        $this->assertSame('german_normalization', (new GermanNormalization)->type());
        $this->assertSame(['named', ['type' => 'keyword']], name_configs(['named' => ['type' => 'keyword']]));

        $category = new Category('category');
        $category->analyze(new NewAnalyzer(new Analysis, 'category_analyzer'));

        $this->assertTrue($category->isSortable());
        $this->assertSame('category.sortable', $category->sortableName());

        $range = new Range('span');
        $aggs = new Aggs;
        $range->aggregation($aggs, '10');

        $this->assertFalse($range->isFacetable());
        $this->assertNull($range->facets([]));
        $this->assertSame([], $aggs->toRaw());
        $denseVector = new DenseVector('vector', 3);

        $this->assertSame('concatenate', $denseVector->strategy()->value);
        $this->assertSame(3, (new ElasticsearchNestedVector('nested_vector', 3))->dims());
        $this->assertSame(4, (new NestedVector('nested_vector', 4, 'test-embeddings'))->dims());

        $mappingCharFilter = new MappingCharFilter('mapping');
        $mappingCharFilter->settings(['a=>b']);

        $this->assertSame(['mapping' => ['type' => 'mapping', 'mappings' => ['0 => a=>b']]], $mappingCharFilter->toRaw());

        $analyzer = new Analyzer('custom');
        $analyzer->addFilters(['trim' => new Trim('trim')]);
        $analyzer->addCharFilters(['mapping' => $mappingCharFilter]);
        $analyzer->removeFilter('trim');
        $analyzer->removeCharFilter('mapping');

        $this->assertSame(['custom' => ['tokenizer' => 'standard', 'char_filter' => [], 'filter' => []]], $analyzer->toRaw());

        $semantic = new Text('semantic_title');
        $semantic->semantic('test-embeddings', dimensions: 128);
        $semantic->meta(['role' => 'primary']);
        $semantic->analyze(new NewAnalyzer(new Analysis, 'noop_analyzer'));
        $baseVector = new BaseVector('manual_vector', 5);
        $vectorProperty = new ReflectionProperty($semantic, 'vectors');
        $vectorProperty->setValue($semantic, [$baseVector]);

        $this->assertSame(['role' => 'primary'], $semantic->getMeta());
        $this->assertSame([$baseVector], $semantic->vectorFields()->toArray());
        $properties = new Properties(fields: ['semantic_title' => $semantic]);
        $embeddingsType = new Embeddings($properties, $this->elasticsearchConnection->driver());

        $this->assertSame('_embeddings', $embeddingsType->name());
        $this->assertArrayHasKey('_embeddings', $embeddingsType->toRaw());

        $namedQuery = new NewQuery($this->elasticsearchConnection);
        $searchName = new ReflectionProperty($namedQuery, 'searchName');
        $searchName->setValue($namedQuery, 'named_search');

        $this->assertSame('named_search', $namedQuery->getName());
        $suggestion = new SuggestionTypeBuilder;

        $this->assertSame($suggestion, $suggestion->name('suggestion'));
        $this->assertNull($suggestion->term());
        $this->assertSame([
            'match_phrase_prefix' => [
                'title' => [
                    'query' => 'prefix',
                    'boost' => 1.0,
                    'analyzer' => 'default',
                ],
            ],
        ], (new MatchPhrasePrefix('title', 'prefix'))->fuzziness('AUTO')->toRaw());
        $this->assertSame(1.0, (new FunctionScore(new MatchAll, 'return 1;'))->boost(2)->toRaw()['function_score']['boost']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Reranking is only available on SigmieSearchResponse.');

        $formatter->rerank($this->rerankApi, ['title']);
    }

    /**
     * @test
     */
    public function name_config_errors_are_backed_by_elasticsearch_hits(): void
    {
        $this->assertUtilitySearchHit();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Too many values in name configs');

        name_configs(['one' => [], 'two' => []]);
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
