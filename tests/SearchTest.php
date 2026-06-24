<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Generator;
use Http\Promise\Promise;
use Sigmie\Document\Document;
use Sigmie\Document\Hit;
use Sigmie\Document\RerankedHit;
use Sigmie\Languages\English\English;
use Sigmie\Languages\German\German;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\BaseVector;
use Sigmie\Parse\InputParser;
use Sigmie\Parse\ParseException;
use Sigmie\Query\Aggregations\Metrics\Composite;
use Sigmie\Query\Aggs;
use Sigmie\Query\Queries\Term\Range;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Search\Formatters\RawElasticsearchFormat;
use Sigmie\Search\NewSearch;
use Sigmie\Search\VectorPool;
use Sigmie\Shared\Collection;
use Sigmie\Testing\TestCase;

class SearchTest extends TestCase
{
    /**
     * @test
     */
    public function weighted_query_string(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['name' => 'Mickey']),
            new Document(['name' => 'Goofy']),
            new Document(['name' => 'Donald']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->textScoreMultiplier(2)
            ->semanticScoreMultiplier(0.5)
            ->queryString('Mickey', weight: 2)
            ->queryString('Goofy', weight: 1);

        $this->assertSame(['Mickey', 'Goofy'], array_map(
            fn (object $queryString): string => (string) $queryString,
            $search->queryStrings()
        ));
        $this->assertSame(['name'], $search->getProperties()?->fieldNames());

        $res = $search->get();

        $hits = $res->json('hits');

        $this->assertEquals('Mickey', $hits[0]['_source']['name']);
        $this->assertEquals(2, $res->total());

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('Mickey', weight: 2)
            ->queryString('Goofy', weight: 3)
            ->get();

        $hits = $res->json('hits');

        $this->assertEquals('Goofy', $hits[0]['_source']['name']);
        $this->assertEquals(2, $res->total());

        $raw = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->formatter(new RawElasticsearchFormat)
            ->queryString('Donald')
            ->get();

        $this->assertSame('Donald', $raw->format()['hits']['hits'][0]['_source']['name']);
    }

    /**
     * @test
     */
    public function autocomplete_prefix_returns_completion_suggestions_from_elasticsearch(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->completion('title');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Star Trek']),
                new Document(['title' => 'Star Wars']),
                new Document(['title' => 'Stargate']),
                new Document(['title' => 'Moonrise']),
            ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->autocompletePrefix('star')
            ->autocompleteSize(3)
            ->queryString('')
            ->get();

        $suggestions = array_map(
            fn (array $option): string => $option['text'],
            $res->autocompletion()[0]['options']
        );

        sort($suggestions);

        $this->assertSame(['Star Trek', 'Star Wars', 'Stargate'], $suggestions);
    }

    /**
     * @test
     */
    public function search_response_helpers_reflect_elasticsearch_results(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');
        $blueprint->keyword('category');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Alpha Search Manual', 'category' => 'docs'], _id: 'alpha'),
                new Document(['title' => 'Beta Archive', 'category' => 'archive'], _id: 'beta'),
            ]);

        $response = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('Alpha')
            ->fields(['title'])
            ->facets('category')
            ->size(1)
            ->get();

        $format = $response->format();
        $hits = $response->hits();
        $reranked = $response->rerank('test-rerank', ['title']);
        $rerankedWithApi = $response->rerank($this->rerankApi, ['title'], query: 'Alpha', topK: 1);
        $scores = $this->rerankApi->rerank(['Alpha Search Manual', 'Beta Archive'], 'Alpha');

        $this->assertSame('alpha', $response->json('hits')[0]['_id']);
        $this->assertSame('alpha', $hits[0]->_id);
        $this->assertInstanceOf(Hit::class, $hits[0]);
        $this->assertEquals(1, $response->total());
        $this->assertSame(['Alpha'], $format['query_strings']);
        $this->assertSame('category', $format['facets_string']);
        $this->assertSame(1, $format['page']);
        $this->assertSame(1, $format['per_page']);
        $this->assertNotNull($response->getContext());
        $this->assertInstanceOf(RerankedHit::class, $reranked[0]);
        $this->assertSame('alpha', $reranked[0]->_id);
        $this->assertSame([], $response->autocompletion());
        $this->assertInstanceOf(RerankedHit::class, $rerankedWithApi[0]);
        $this->assertSame([0, 1], array_column($scores, 'index'));
        $this->rerankApi->assertRerankWasCalledWith('Alpha', 1);
        $this->rerankApi->assertRerankWasCalledWithDocumentCount(1);
    }

    /**
     * @test
     */
    public function make_facet_search_returns_filtered_elasticsearch_aggregations(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('category');
        $blueprint->bool('active');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['category' => 'docs', 'active' => true]),
                new Document(['category' => 'docs', 'active' => false]),
                new Document(['category' => 'blog', 'active' => true]),
            ]);

        $response = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->filters('active:true')
            ->facets('category')
            ->makeFacetSearch()
            ->get();

        $facets = $blueprint->get()['category']->facets($response->get()['aggregations']);

        $this->assertSame(['blog' => 1, 'docs' => 1], $facets);
    }

    /**
     * @test
     */
    public function semantic_search_uses_seeded_vector_pool_and_returns_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 128, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Alpha search manual'], _id: 'alpha'),
                new Document(['title' => 'Beta archive notes'], _id: 'beta'),
            ]);

        $vectorPool = new VectorPool($this->embeddingApi);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->semantic()
            ->queryString('Alpha search manual')
            ->fields(['title'])
            ->setVectorPool($vectorPool)
            ->setVectorPool([
                'Gamma cache seed' => [
                    128 => $this->embeddingApi->embed('Gamma cache seed', 128),
                ],
            ]);

        $this->assertSame($vectorPool, $search->getVectorPool('test-embeddings'));
        $this->assertSame($vectorPool, $search->getVectorPool());
        $this->assertArrayHasKey('test-embeddings', $search->getVectorPools());

        $hits = $search->size(1)->hits();

        $this->assertSame('alpha', $hits[0]->_id);
        $this->embeddingApi->assertEmbedWasCalled(1);
        $this->embeddingApi->assertEmbedWasCalledWith('Gamma cache seed');
        $this->embeddingApi->assertEmbedWasCalledWith('Gamma cache seed', 128);
        $this->embeddingApi->assertBatchEmbedWasCalledWith('Gamma cache seed');

        $this->assertCount(128, $this->embeddingApi->promiseEmbed('Alpha search manual', 128)->wait());
        $this->embeddingApi->assertEmbedWasCalled(2);
        $this->embeddingApi->assertEmbedWasCalled();
        $this->assertNotSame('', $this->embeddingApi->model());
        $this->assertGreaterThan(0, $this->embeddingApi->maxBatchSize());
        $this->assertSame(2, $this->embeddingApi->overrideMaxBatchSize(2)->maxBatchSize());
    }

    /**
     * @test
     */
    public function semantic_search_without_embedding_api_falls_back_to_elasticsearch_keyword_hits(): void
    {
        $indexName = uniqid();

        $indexBlueprint = new NewProperties;
        $indexBlueprint->text('title');

        $searchBlueprint = new NewProperties;
        $searchBlueprint->text('title')->newSemantic(fn ($semantic) => $semantic->accuracy(1, 128));

        $this->sigmie->newIndex($indexName)
            ->properties($indexBlueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($indexBlueprint)
            ->merge([
                new Document(['title' => 'API-less semantic fallback'], _id: 'matching'),
                new Document(['title' => 'Other document'], _id: 'missing'),
            ]);

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($searchBlueprint)
            ->semantic()
            ->queryString('fallback')
            ->hits();

        $this->assertSame(['matching'], array_map(fn ($hit): string => $hit->_id, $hits));

        $search = new class($this->elasticsearchConnection) extends NewSearch
        {
            public function noResultsEmptyQuery(): array
            {
                $this->noResultsOnEmptySearch();

                return $this->onEmptyQueryString()->toRaw();
            }

            public function vectorDimensions(): array
            {
                return $this->getVectorDimensions(new Collection([
                    new BaseVector('first', dims: 3),
                    new BaseVector('second', dims: 3),
                    new BaseVector('third', dims: 5),
                ]));
            }
        };

        $emptyQuery = $search->noResultsEmptyQuery();

        $this->assertArrayHasKey('match_none', $emptyQuery);
        $this->assertSame(1.0, $emptyQuery['match_none']->boost);
        $this->assertSame([3, 5], array_values($search->vectorDimensions()));
    }

    /**
     * @test
     */
    public function field_scoped_query_string(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');
        $blueprint->text('category');
        $blueprint->text('description');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['name' => 'Laptop', 'category' => 'Electronics', 'description' => 'High-end device']),
                new Document(['name' => 'Phone', 'category' => 'Electronics', 'description' => 'Mobile phone']),
                new Document(['name' => 'Electronics', 'category' => 'Furniture', 'description' => 'Office desk']),
            ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->fields(['name', 'category'])
            ->queryString('Electronics', 1.0, ['category'])
            ->makeSearch();

        $hits = $search->get()->hits();

        // Only two have category Electronics the third one
        // has category Furniture, and Elactronies in name
        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function find_without_dash(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->searchableNumber('number');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'number' => '08000234379',
            ]),
        ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('0800-0234379')
            ->get();

        $this->assertNotEmpty($res->hits());
    }

    /**
     * @test
     */
    public function handle_boost_missing_gracefully(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->date('date');

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'date' => '2024-01-01',
            ]),
        ]);

        $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->sort('_score')
            ->queryString('')
            ->get();

        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function empty_on_non_queriable_field(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->date('date');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'date' => '2024-01-01',
            ]),
        ]);

        $saved = $this->sigmie->newSearch($indexName)
            ->fields(['date'])
            ->properties($blueprint)
            ->queryString('123');

        $hits = $saved->get()->json('hits');

        $this->assertEmpty($hits);
    }

    /**
     * @test
     */
    public function id_search(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->id('id');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'id' => '123',
            ]),
        ]);

        $saved = $this->sigmie->newSearch($indexName)
            ->fields(['id'])
            ->properties($blueprint)
            ->queryString('123');

        $hits = $saved->get()->json('hits');

        $this->assertNotEmpty($hits);
    }

    /**
     * @test
     */
    public function nested_retrieve(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->name('name');
        $blueprint->nested('contact', function (NewProperties $blueprint): void {
            $blueprint->name('name');
            $blueprint->nested('dog', function (NewProperties $blueprint): void {
                $blueprint->name('name');
            });
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Mickey',
                'contact' => [
                    'name' => 'Mickey',
                    'dog' => [
                        'name' => 'Pluto',
                    ],
                ],
            ]),
        ]);

        $saved = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('Pluto')
            ->fields(['contact.dog.name'])
            ->retrieve(['contact.dog.name']);

        $response = $saved->get();

        $hits = $response->json('hits');

        $this->assertNull($hits[0]['_source']['contact']['name'] ?? null);
        $this->assertNotNull($hits[0]['_source']['contact']['dog']['name'] ?? null);
    }

    /**
     * @test
     */
    public function nested_name_property(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->name('name');
        $blueprint->nested('contact', function (NewProperties $blueprint): void {
            $blueprint->name('name');
            $blueprint->nested('dog', function (NewProperties $blueprint): void {
                $blueprint->name('name');
            });
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Mickey',
                'contact' => [
                    'name' => 'Mickey',
                    'dog' => [
                        'name' => 'Pluto',
                    ],
                ],
            ]),
        ]);

        $saved = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('Pluto')
            ->fields(['contact.dog.name']);

        $hits = $saved->get()->json('hits');

        $this->assertNotEmpty($hits);
    }

    /**
     * @test
     */
    public function min_score(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->title();

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'title' => 'Mickey',
            ]),
        ]);

        $response = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->weight([
                'title' => 10,
            ])
            ->minScore(2)
            ->queryString('Mickey')
            ->get();

        $hits = $response->hits();

        $this->assertGreaterThan(2, $hits[0]->_score ?? 0);
    }

    /**
     * @test
     */
    public function price_facet(): void
    {

        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->price();

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $res = $this->indexAPICall($indexName, 'GET');

        $this->assertEquals('price', $res->json($index->name.'.mappings.properties.price.meta.type'));

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['price' => 200]),
            new Document(['price' => 100]),
            new Document(['price' => 50]),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('')
            ->facets('price')
            ->get();

        $price = (array) $search->json('facets')['price'];

        $buckets = $price['histogram'] ?? [];

        $this->assertCount(16, $buckets);

        $this->assertEquals(50, $price['min']);
        $this->assertEquals(200, $price['max']);
    }

    /**
     * @test
     */
    public function empty_results_on_empty_string(): void
    {
        $indexName = uniqid();
        $blueprint = new NewProperties;
        $blueprint->name('name');

        $index = $this->sigmie->newIndex($indexName)
            ->trim()
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Jason Preston',
                'autocomplete' => [''],
            ]),
        ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('')
            ->get();

        $hits = $res->json('hits');

        $this->assertNotEmpty($hits);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->noResultsOnEmptySearch()
            ->queryString('')
            ->get();

        $hits = $res->json('hits');

        $this->assertEmpty($hits);
    }

    /**
     * @test
     */
    public function name_prefix_search_test(): void
    {
        $indexName = uniqid();
        $blueprint = new NewProperties;
        $blueprint->name('name');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Jason Preston',
            ]),
        ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('jas')
            ->fields(['name'])
            ->retrieve(['name'])
            ->get();

        $this->sigmie->index($indexName)->raw;

        $hits = $res->json('hits');

        $this->assertNotEmpty($hits);
    }

    /**
     * @test
     */
    public function name_two_words_search_test(): void
    {
        $documentId = uniqid();
        $indexName = uniqid();
        $blueprint = new NewProperties;
        $blueprint->name('first_name');
        $blueprint->name('last_name');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'first_name' => 'Bam',
                'last_name' => 'Adebayo',
            ], $documentId),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('bam ade')
            ->fields(['first_name', 'last_name'])
            ->retrieve(['first_name', 'last_name'])
            ->makeSearch();

        $res = $search->get();

        $hits = $res->json('hits');

        $this->assertNotEmpty($hits);
    }

    /**
     * @test
     */
    public function name_search_test(): void
    {
        $indexName = uniqid();
        $blueprint = new NewProperties;
        $blueprint->name('name');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Jason Preston',
            ]),
        ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('jas')
            ->fields(['name'])
            ->retrieve(['name'])
            ->get();

        $hits = $res->json('hits');

        $this->assertNotEmpty($hits);
    }

    /**
     * @test
     */
    public function search_promises_test(): void
    {
        $indexName = uniqid();
        $blueprint = new NewProperties;
        $blueprint->text('name');
        $blueprint->text('description');

        $props = ($blueprint)();

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->lowercase()
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Mickey',
                'description' => 'Adventure in the woods',
            ]),
            new Document([
                'name' => 'Goofy',
                'description' => 'Mickey and his friends',
            ]),
            new Document([
                'name' => 'Donald',
                'description' => 'Chasing Goofy',
            ]),
        ]);

        $promise = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->queryString('mickey')
            ->fields(['name'])
            ->retrieve(['name', 'description'])
            ->promise();

        $this->assertInstanceOf(Promise::class, $promise);
    }

    /**
     * @test
     */
    public function with_weight(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');
        $blueprint->text('description')->searchAsYouType();

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Mickey',
                'description' => 'Adventure in the woods',
            ]),
            new Document([
                'name' => 'Goofy',
                'description' => 'Mickey and his friends',
            ]),
            new Document([
                'name' => 'Donald',
                'description' => 'Chasing Goofy',
            ]),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('Mickey')
            ->weight([
                'name' => 5,
                'description' => 1,
            ])
            ->fields(['name', 'description'])
            ->sort('_score')
            ->get();

        $hits = $search->json('hits');

        $this->assertEquals('Mickey', $hits[0]['_source']['name']);
        $this->assertCount(2, $hits);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('Mickey')
            ->fields(['name', 'description'])
            ->sort('_score')
            ->weight([
                'name' => 1,
                'description' => 5,
            ])
            ->get();

        $hits = $search->json('hits');

        $this->assertEquals('Goofy', $hits[0]['_source']['name']);
        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function typo_tolerance_with_one_typo(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');
        $blueprint->category('category');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['name' => 'Mickey', 'category' => 'cartoon']),
            new Document(['name' => 'Goofy', 'category' => 'cartoon']),
            new Document(['name' => 'Donald', 'category' => 'cartoon']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('Mockey')
            ->facets('category')
            ->fields(['name'])
            ->typoTolerance()
            ->typoTolerantAttributes([
                'name',
            ])
            ->get();

        $hits = $search->json('hits');

        $this->assertCount(1, $hits);
        $this->assertSame('Mickey', $hits[0]['_source']['name']);
    }

    /**
     * @test
     */
    public function custom_typo_tolerance_thresholds_return_expected_elasticsearch_hit(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['name' => 'Mickey'], _id: 'mickey'),
            new Document(['name' => 'Donald'], _id: 'donald'),
        ]);

        $response = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->fields(['name'])
            ->typoTolerantAttributes(['name'])
            ->minCharsForOneTypo(1)
            ->minCharsForTwoTypo(4)
            ->queryString('Mickeu')
            ->get();

        $this->assertSame(['mickey'], array_map(
            fn (array $hit): string => $hit['_id'],
            $response->json('hits')
        ));
    }

    /**
     * @test
     */
    public function typo_tolerance_without_typo(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['name' => 'Mickey']),
            new Document(['name' => 'Goofy']),
            new Document(['name' => 'Donald']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('Mockey')
            ->fields(['name'])
            ->get();

        $hits = $search->json('hits');

        $this->assertCount(0, $hits);
    }

    /**
     * @test
     */
    public function source(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');
        $blueprint->text('category')->keyword();

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['category' => 'a', 'name' => 'Mickey']),
            new Document(['category' => 'b', 'name' => 'Goofy']),
            new Document(['category' => 'c', 'name' => 'Donald']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('a')
            ->retrieve(['name'])
            ->fields(['category'])
            ->get();

        $hits = $search->json('hits');

        $this->assertArrayHasKey('name', $hits[0]['_source']);
        $this->assertArrayNotHasKey('category', $hits[0]['_source']);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('a')
            ->fields(['category'])
            ->get();

        $hits = $search->json('hits');

        $this->assertArrayHasKey('name', $hits[0]['_source']);
        $this->assertArrayHasKey('category', $hits[0]['_source']);
    }

    /**
     * @test
     */
    public function highlight(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('category')->keyword();

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['category' => 'a']),
            new Document(['category' => 'b']),
            new Document(['category' => 'c']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('a')
            ->highlighting(['category'], '<span class="font-bold">', '</span>')
            ->fields(['category'])
            ->get();

        $hits = $search->json('hits');

        $this->assertEquals('<span class="font-bold">a</span>', $hits[0]['highlight']['category'][0]);
    }

    /**
     * @test
     */
    public function sort(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('category')->keyword()->makeSortable();

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['category' => 'a']),
            new Document(['category' => 'b']),
            new Document(['category' => 'c']),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->sort('category:desc')
            ->fields(['name', 'description'])
            ->get();

        $hits = $search->json('hits');

        $this->assertEquals('c', $hits[0]['_source']['category']);
        $this->assertEquals('b', $hits[1]['_source']['category']);
        $this->assertEquals('a', $hits[2]['_source']['category']);
    }

    /**
     * @test
     */
    public function filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->bool('active');

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['active' => true]),
            new Document(['active' => false]),
            new Document(['active' => true]),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->filters('active:true')
            ->fields(['name', 'description'])
            ->get();

        $hits = $search->json('hits');

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function size(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->bool('active');

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['active' => true]),
            new Document(['active' => false]),
            new Document(['active' => true]),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->size(2)
            ->fields(['name', 'description'])
            ->get();

        $hits = $search->json('hits');

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function query(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');
        $blueprint->text('description');

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Narnia',
                'description' => 'Awesome',
            ]),
            new Document([
                'name' => 'Disneyland',
                'description' => 'Too Good',
            ]),
            new Document([
                'name' => 'Neverland',
                'description' => 'Good',
            ]),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('Good')
            ->fields(['name', 'description'])
            ->get();

        $hits = $search->json('hits');

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function matches_all_on_empty_string(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');
        $blueprint->text('description');

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Narnia',
                'description' => 'Awesome',
            ]),
            new Document([
                'name' => 'Disneyland',
                'description' => 'Too Good',
            ]),
            new Document([
                'name' => 'Neverland',
                'description' => 'Good',
            ]),
        ]);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->fields(['name', 'description'])
            ->get();

        $hits = $search->json('hits');

        $this->assertCount(3, $hits);
    }

    /**
     * @test
     */
    public function search_test(): void
    {
        $indexName = uniqid();
        $properties = new NewProperties;
        $properties->text('name');
        $properties->text('description');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($properties)
            ->lowercase()
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Mickey',
                'description' => 'Adventure in the woods',
            ]),
            new Document([
                'name' => 'Goofy',
                'description' => 'Mickey and his friends',
            ]),
            new Document([
                'name' => 'Donald',
                'description' => 'Chasing Goofy',
            ]),
        ]);

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($properties)
            ->queryString('mickey')
            ->fields(['name'])
            ->retrieve(['name', 'description'])
            ->get()
            ->json('hits');

        $this->assertNotEmpty($hits);
    }

    /**
     * @test
     */
    public function hits(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['name' => 'Mickey']),
            new Document(['name' => 'Goofy']),
            new Document(['name' => 'Donald']),
        ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('Mickey', weight: 2)
            ->queryString('Goofy', weight: 1)
            ->get();

        $hits = $res->hits();

        $this->assertEquals('Mickey', $hits[0]['name']);
        $this->assertEquals(2, $res->total());
    }

    /**
     * @test
     */
    public function no_keyword_search_flag(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->title('name')->semantic(dimensions: 384, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document([
                    'name' => ['King', 'Prince'],
                    'age' => 10,
                ]),
                new Document([
                    'name' => 'Queen',
                    'age' => 20,
                ]),
            ]);

        $response = $this->sigmie
            ->newSearch($indexName)
            ->noResultsOnEmptySearch()
            ->disableKeywordSearch()
            ->properties($blueprint)
            ->queryString('Woman')
            ->get();

        $this->assertEquals(0, $response->total());
    }

    /**
     * @test
     */
    public function direct_query_string_vector_searches_elasticsearch_semantic_field(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(dimensions: 384, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Alpha Manual'], _id: 'alpha'),
                new Document(['title' => 'Beta Handbook'], _id: 'beta'),
            ]);

        $vector = $this->embeddingApi->embed('Alpha Manual', 384);

        $search = $this->sigmie
            ->newSearch($indexName)
            ->properties($blueprint)
            ->semantic()
            ->disableKeywordSearch()
            ->queryString('ignored text', fields: ['title'])
            ->size(1);

        $queryString = $search->queryStrings()[0];
        $queryString->setDimension(384)->setVector($vector);

        $hits = $search->hits();

        $this->assertSame('alpha', $hits[0]->_id);
        $this->assertSame('ignored text', (string) $queryString);
        $this->assertTrue($queryString->hasFields());
        $this->assertSame([
            'text' => 'ignored text',
            'weight' => 1.0,
            'dimension' => 384,
            'vector' => $vector,
            'fields' => ['title'],
        ], $queryString->toArray());
    }

    /**
     * @test
     */
    public function semantic_search_scoped_to_non_vector_field_returns_sorted_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title')->keyword()->makeSortable();
        $blueprint->text('description')->semantic(dimensions: 384, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie
            ->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document([
                    'title' => 'Beta',
                    'description' => 'Second document',
                ], _id: 'beta'),
                new Document([
                    'title' => 'Alpha',
                    'description' => 'First document',
                ], _id: 'alpha'),
            ]);

        $hits = $this->sigmie
            ->newSearch($indexName)
            ->properties($blueprint)
            ->semantic()
            ->queryString('Alpha', fields: ['title'])
            ->sort('title:asc')
            ->size(2)
            ->get()
            ->json('hits');

        $this->assertSame(['alpha'], array_map(
            fn (array $hit): string => $hit['_id'],
            $hits
        ));
    }

    /**
     * @test
     */
    public function boost_field_search_test(): void
    {
        $indexName = uniqid();
        $blueprint = new NewProperties;
        $blueprint->boost();

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'boost' => 4,
            ]),
            new Document([
                'boost' => 10,
            ]),
        ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('')
            ->get();

        $hits = $res->json('hits');

        $this->assertEquals(10, $hits[0]['_source']['boost']);
    }

    /**
     * @test
     */
    public function no_boost_field_search_test(): void
    {
        $indexName = uniqid();
        $blueprint = new NewProperties;
        $blueprint->number('boost');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'boost' => 4,
            ]),
            new Document([
                'boost' => 10,
            ]),
        ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('')
            ->get();

        $hits = $res->json('hits');

        $this->assertEquals(4, $hits[0]['_source']['boost']);
    }

    /**
     * @test
     */
    public function multi_lang_search(): void
    {
        $deIndexName = uniqid('de');
        $enIndexName = uniqid('en');

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $this->sigmie->newIndex($deIndexName)
            ->properties($blueprint)
            ->language(new German)
            // without normalize it's not found
            ->germanNormalize()
            ->create();

        $this->sigmie->newIndex($enIndexName)
            ->properties($blueprint)
            ->language(new English)
            ->create();

        $deDocs = [
            new Document([
                'name' => 'tür',
            ]),
        ];

        $enDocs = [
            new Document([
                'name' => 'door',
            ]),
        ];

        $this->sigmie->collect($deIndexName, refresh: true)->merge($deDocs);
        $this->sigmie->collect($enIndexName, refresh: true)->merge($enDocs);

        $res = $this->sigmie->newSearch(sprintf('%s,%s', $deIndexName, $enIndexName))
            ->properties($blueprint)
            ->queryString('door tur')
            ->get();

        $this->assertEquals(2, $res->total());
    }

    /**
     * @test
     */
    public function range_query(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->range('numbers')->integer()
            ->withQueries(fn (string $queryString): array => [
                new Range('numbers', [
                    '>=' => $queryString,
                    '<=' => $queryString,
                ]),
            ]);

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

        $response = $this->sigmie
            ->newSearch($indexName)
            ->noResultsOnEmptySearch()
            ->properties($blueprint)
            ->queryString('9')
            ->get();

        $this->assertEquals(0, $response->total());
    }

    /**
     * @test
     */
    public function response_code_is_populated(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->merge([
                new Document(['name' => 'Test Document']),
            ]);

        $response = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('Test')
            ->get();

        $this->assertEquals(200, $response->code());
    }

    /**
     * @test
     */
    public function lazy_returns_generator(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $gen = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->lazy();

        $this->assertInstanceOf(Generator::class, $gen);
    }

    /**
     * @test
     */
    public function lazy_iterates_all_documents_across_pages(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $docs = [];
        for ($i = 0; $i < 5; $i++) {
            $docs[] = new Document(['name' => 'item'.$i]);
        }

        $this->sigmie->collect($indexName, refresh: true)->merge($docs);

        $hits = iterator_to_array(
            $this->sigmie->newSearch($indexName)
                ->properties($blueprint)
                ->chunk(2)
                ->lazy()
        );

        $this->assertCount(5, $hits);
        foreach ($hits as $hit) {
            $this->assertInstanceOf(Hit::class, $hit);
        }
    }

    /**
     * @test
     */
    public function each_respects_filters_for_lazy_iteration(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->bool('active');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['active' => true]),
            new Document(['active' => false]),
            new Document(['active' => true]),
            new Document(['active' => true]),
        ]);

        $ids = [];

        $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->filters('active:true')
            ->chunk(2)
            ->each(function (Hit $hit) use (&$ids): void {
                $this->assertInstanceOf(Hit::class, $hit);
                $ids[] = $hit->_id;
            });

        $this->assertCount(3, $ids);
    }

    /**
     * @test
     */
    public function each_respects_query_string_for_lazy_iteration(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['name' => 'Mickey']),
            new Document(['name' => 'Goofy']),
            new Document(['name' => 'Mickey Mouse']),
        ]);

        $names = [];

        $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('Mickey')
            ->each(function (Hit $hit) use (&$names): void {
                $names[] = $hit->_source['name'];
            });

        $this->assertCount(2, $names);
        $this->assertContains('Mickey', $names);
        $this->assertContains('Mickey Mouse', $names);
    }

    /**
     * @test
     */
    public function unique_by_collapses_results_by_field(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('product_id');
        $blueprint->integer('ord');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['product_id' => 'A', 'ord' => 1]),
            new Document(['product_id' => 'A', 'ord' => 2]),
            new Document(['product_id' => 'B', 'ord' => 3]),
        ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('')
            ->sort('ord:asc')
            ->uniqueBy('product_id')
            ->get();

        $hits = $res->json('hits');

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function unique_by_with_top_returns_inner_hits(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('product_id');
        $blueprint->integer('ord');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['product_id' => 'A', 'ord' => 1]),
            new Document(['product_id' => 'A', 'ord' => 2]),
            new Document(['product_id' => 'A', 'ord' => 3]),
            new Document(['product_id' => 'B', 'ord' => 0]),
        ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('')
            ->sort('ord:asc')
            ->uniqueBy('product_id', top: 2)
            ->get();

        $hits = $res->json('hits');
        $this->assertCount(2, $hits);

        $groupA = null;
        foreach ($hits as $hit) {
            if ($hit['_source']['product_id'] === 'A') {
                $groupA = $hit;
                break;
            }
        }

        $this->assertNotNull($groupA);
        $this->assertArrayHasKey('inner_hits', $groupA);
        $this->assertArrayHasKey('top', $groupA['inner_hits']);
        $this->assertCount(
            2,
            $groupA['inner_hits']['top']['hits']['hits']
        );
    }

    /**
     * @test
     */
    public function filter_query_ands_a_hard_clause_with_the_string_filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->name('name');
        $blueprint->keyword('category');
        $blueprint->bool('active');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['name' => 'Alice', 'category' => 'public', 'active' => true]),
            new Document(['name' => 'Bob', 'category' => 'public', 'active' => false]),
            new Document(['name' => 'Carol', 'category' => 'private', 'active' => true]),
        ]);

        // Baseline: the OR string filter alone matches all three documents.
        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('')
            ->filters("category:'public' OR category:'private'")
            ->get();

        $this->assertEquals(3, $res->total());

        // The hard clause is ANDed on top without corrupting the OR (should)
        // semantics: only the active documents from either category survive.
        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('')
            ->filters("category:'public' OR category:'private'")
            ->filterQuery(new Term('active', true))
            ->get();

        $names = array_map(fn (array $hit): string => $hit['_source']['name'], $res->json('hits'));

        sort($names);

        $this->assertEquals(2, $res->total());
        $this->assertEquals(['Alice', 'Carol'], $names);
    }

    /**
     * @test
     */
    public function filter_query_constrains_facet_counts(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->name('name');
        $blueprint->keyword('category');
        $blueprint->bool('active');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['name' => 'Alice', 'category' => 'public', 'active' => true]),
            new Document(['name' => 'Bob', 'category' => 'public', 'active' => false]),
            new Document(['name' => 'Carol', 'category' => 'private', 'active' => true]),
        ]);

        // The hard clause lives in the query, so the facet aggregation is
        // scoped by it too: the inactive 'public' document is not counted.
        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('')
            ->facets('category')
            ->filterQuery(new Term('active', true))
            ->get();

        $props = $blueprint();

        $facets = $props['category']->facets($res->facetAggregations());

        $this->assertEquals(['private' => 1, 'public' => 1], $facets);
    }

    /**
     * @test
     */
    public function filters_throw_on_error_can_be_opted_into(): void
    {
        $blueprint = new NewProperties;
        $blueprint->keyword('category');

        $this->expectException(ParseException::class);

        $this->sigmie->newSearch('test')
            ->properties($blueprint)
            ->filters("nonexistent:'x'", throwOnError: true);
    }

    /**
     * @test
     */
    public function filters_collect_errors_by_default_instead_of_throwing(): void
    {
        $blueprint = new NewProperties;
        $blueprint->keyword('category');

        $search = $this->sigmie->newSearch('test')
            ->properties($blueprint)
            ->filters("nonexistent:'x'");

        $this->assertNotEmpty($search->filterParser->errors());
    }

    /**
     * @test
     */
    public function new_search_returns_custom_aggregations_from_real_index(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('category');
        $blueprint->bool('active');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['category' => 'public', 'active' => true]),
                new Document(['category' => 'public', 'active' => false]),
                new Document(['category' => 'private', 'active' => true]),
            ]);

        $response = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('')
            ->filterQuery(new Term('active', true))
            ->aggregate(fn (Aggs $aggs): Composite => $aggs->composite(
                'categories',
                [
                    [
                        'category' => [
                            'terms' => [
                                'field' => 'category',
                            ],
                        ],
                    ],
                ],
                10,
            ))
            ->get();

        $buckets = $response->aggregation('categories.buckets');

        $this->assertIsArray($buckets);
        $this->assertEquals(['private', 'public'], array_map(
            fn (array $bucket): string => $bucket['key']['category'],
            $buckets,
        ));
        $this->assertEquals([1, 1], array_map(
            fn (array $bucket): int => $bucket['doc_count'],
            $buckets,
        ));
        $this->assertEquals(2, $response->total());
    }

    /**
     * @test
     */
    public function input_parser_parts_drive_elasticsearch_search(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');
        $blueprint->category('category');
        $blueprint->number('rank');

        $this->sigmie->newIndex($indexName)
            ->lowercase()
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->merge([
                new Document(['title' => 'Beta Public Guide', 'category' => 'public', 'rank' => 2]),
                new Document(['title' => 'Beta Private Guide', 'category' => 'private', 'rank' => 1]),
                new Document(['title' => 'Alpha Public Guide', 'category' => 'public', 'rank' => 3]),
            ]);

        $parsed = (new InputParser)->parse("Beta FILTER category:'public' SORT rank:desc");

        $response = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString($parsed['query_string'])
            ->filters($parsed['filter_string'])
            ->sort($parsed['sort_string'])
            ->get();

        $this->assertSame(1, $response->total());
        $this->assertSame('Beta Public Guide', $response->hits()[0]->_source['title']);
    }

    /**
     * @test
     */
    public function facets_throw_on_error_can_be_opted_into(): void
    {
        $blueprint = new NewProperties;
        $blueprint->keyword('category');

        $this->expectException(ParseException::class);

        $this->sigmie->newSearch('test')
            ->properties($blueprint)
            ->facets('category', "nonexistent:'x'", throwOnError: true);
    }

    /**
     * @test
     */
    public function new_search_without_properties_guard_paths_are_backed_by_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->add(new Document(['title' => 'New search guard coverage'], _id: 'matching'));

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->fields(['title'])
            ->queryString('guard')
            ->hits();

        $this->assertSame(['matching'], array_map(fn ($hit): string => $hit->_id, $hits));

        $search = new class($this->elasticsearchConnection) extends NewSearch
        {
            public function hasSemanticFieldCoverage(): bool
            {
                return $this->hasSemanticFields();
            }

            public function populateVectorPoolCoverage(): void
            {
                $this->populateVectorPool();
            }

            public function vectorFieldsCoverage(): Collection
            {
                return $this->getVectorFields();
            }

            public function requiredApisCoverage(): array
            {
                return $this->getRequiredEmbeddingApis();
            }
        };

        $this->assertFalse($search->hasSemanticFieldCoverage());
        $this->assertSame([], $search->vectorFieldsCoverage()->toArray());
        $this->assertSame([], $search->requiredApisCoverage());

        $searchWithRequiredApis = new class($this->elasticsearchConnection) extends NewSearch
        {
            public function populateVectorPoolCoverage(): void
            {
                $this->populateVectorPool();
            }

            protected function getRequiredEmbeddingApis(): array
            {
                return ['missing-api'];
            }
        };

        $searchWithRequiredApis->populateVectorPoolCoverage();
    }

    /**
     * @test
     */
    public function hit_serialization_paths_are_backed_by_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)
            ->properties($blueprint)
            ->add(new Document(['title' => 'Hit serialization'], _id: 'matching'));

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->fields(['title'])
            ->queryString('serialization')
            ->hits();

        $this->assertSame(['matching'], array_map(fn ($hit): string => $hit->_id, $hits));

        $hit = new Hit(['title' => 'Hit serialization'], 'matching', 1.5, $indexName);
        $reranked = new RerankedHit($hit, 0.9);

        $this->assertSame([
            '_id' => 'matching',
            '_score' => 1.5,
            '_source' => ['title' => 'Hit serialization'],
        ], $hit->toArray());
        $this->assertSame([
            '_id' => 'matching',
            '_score' => 1.5,
            '_source' => ['title' => 'Hit serialization'],
            '_rerank_score' => 0.9,
        ], $reranked->toArray());
    }
}
