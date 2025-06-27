<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Http\Promise\Promise;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\Price;
use Sigmie\Sigmie;
use Sigmie\Testing\TestCase;

class SearchTest extends TestCase
{

    /**
     * @test
     */
    public function v8_knn_search()
    {
        // TODO
        // This was failing because boost out of knn query
    }

    /**
     * @test
     */
    public function search_template_with_query_weight()
    {
    }


    /**
     * @test
     */
    public function weighted_query_string()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
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

        $hits = $res->json('hits.hits');

        $this->assertEquals('Mickey', $hits[0]['_source']['name']);
        $this->assertEquals(2, $res->total());

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('Mickey', weight: 2)
            ->queryString('Goofy', weight: 3)
            ->get();

        $hits = $res->json('hits.hits');

        $this->assertEquals('Goofy', $hits[0]['_source']['name']);
        $this->assertEquals(2, $res->total());
    }

    /**
     * @test
     */
    public function find_without_dash()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
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
    public function handle_boost_missing_gracefully()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->date('date');

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'date' => '2024-01-01',
            ]),
        ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->sort('_score')
            ->queryString('')
            ->get();

        $this->assertEquals(200, $res->code());
    }

    /**
     * @test
     */
    public function empty_on_non_queriable_field()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
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

        $hits = $saved->get()->json('hits.hits');

        $this->assertEmpty($hits);
    }

    /**
     * @test
     */
    public function id_search()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
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

        $hits = $saved->get()->json('hits.hits');

        $this->assertNotEmpty($hits);
    }

    /**
     * @test
     */
    public function nested_retrieve()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->name('name');
        $blueprint->nested('contact', function (NewProperties $blueprint) {
            $blueprint->name('name');
            $blueprint->nested('dog', function (NewProperties $blueprint) {
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

        $hits = $saved->get()->json('hits.hits');

        $this->assertNull($hits[0]['_source']['contact']['name'] ?? null);
        $this->assertNotNull($hits[0]['_source']['contact']['dog']['name'] ?? null);
    }

    /**
     * @test
     */
    public function nested_name_property()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->name('name');
        $blueprint->nested('contact', function (NewProperties $blueprint) {
            $blueprint->name('name');
            $blueprint->nested('dog', function (NewProperties $blueprint) {
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

        $hits = $saved->get()->json('hits.hits');

        $this->assertNotEmpty($hits);
    }

    /**
     * @test
     */
    public function min_score()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
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
                'title' => 5,
            ])
            ->queryString('Mickey')
            ->get();

        $hits = $response->json('hits.hits');

        $this->assertGreaterThan(2, $hits[0]['_score']);
        $this->assertLessThan(3, $hits[0]['_score']);

        $response = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->weight([
                'title' => 5,
            ])
            ->queryString('Mickey')
            ->minScore(3)
            ->get();

        $hits = $response->json('hits.hits');

        $this->assertEmpty($hits);
    }

    /**
     * @test
     */
    public function price_facet()
    {

        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->price();

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $res = $this->indexAPICall($indexName, 'GET');

        $this->assertEquals(Price::class, $res->json("{$index->name}.mappings.properties.price.meta.class"));

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

        $aggs = $search->aggregation('price_histogram')['buckets'] ?? [];

        $this->assertCount(16, $aggs);

        $minAgg = $search->aggregation('price_min');
        $maxAgg = $search->aggregation('price_max');

        $this->assertEquals(50, $minAgg['value']);
        $this->assertEquals(200, $maxAgg['value']);
    }

    /**
     * @test
     */
    public function empty_results_on_empty_string()
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

        $hits = $res->json('hits.hits');

        $this->assertNotEmpty($hits);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->noResultsOnEmptySearch()
            ->queryString('')
            ->get();

        $hits = $res->json('hits.hits');

        $this->assertEmpty($hits);
    }

    /**
     * @test
     */
    public function boost_search_test()
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
                'autocomplete' => [''],
            ]),
        ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('jas')
            ->fields(['name'])
            ->retrieve(['name'])
            ->get();

        $hits = $res->json('hits.hits');

        $this->assertNotEmpty($hits);
    }

    /**
     * @test
     */
    public function name_two_words_search_test()
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
            ->make();

        $query = $search->toRaw()['query']['function_score']['query'];

        $res = $search->get();

        $hits = $res->json('hits.hits');

        $this->assertNotEmpty($hits);
    }

    /**
     * @test
     */
    public function name_search_test()
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

        $hits = $res->json('hits.hits');

        $this->assertNotEmpty($hits);
    }

    /**
     * @test
     */
    public function completion_suggests_test()
    {
        $indexName = uniqid();
        $blueprint = new NewProperties;
        $blueprint->name('name');
        $blueprint->text('description');

        $index = $this->sigmie->newIndex($indexName)
            ->autocomplete(['name', 'description'])
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'Mickey',
                'description' => 'Adventure in the woods',
            ]),
            new Document([
                'name' => 'Minie',
                'description' => 'Adventure in the woods',
            ]),
            new Document([
                'name' => 'Modern',
                'description' => 'Adventure in the woods',
            ]),
            new Document([
                'name' => 'Mice',
                'description' => 'Adventure in the woods',
            ]),
            new Document([
                'name' => 'Marisa',
                'description' => 'Adventure in the woods',
            ]),
        ]);

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('m')
            ->fields(['name'])
            ->retrieve(['name'])
            ->get();

        $suggestions = array_map(fn($value) => $value['text'], $res->json('suggest.autocompletion.0.options'));

        $this->assertEquals([
            'Marisa',
            'Mice',
            'Mickey',
            'Minie',
            'Modern',
        ], $suggestions);
    }

    /**
     * @test
     */
    public function search_promises_test()
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
    public function with_weight()
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

        $hits = $search->json('hits.hits');

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

        $hits = $search->json('hits.hits');

        $this->assertEquals('Goofy', $hits[0]['_source']['name']);
        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function typo_tolerance_with_one_typo()
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

        $hits = $search->json('hits.hits');

        $this->assertCount(1, $hits);
    }

    /**
     * @test
     */
    public function typo_tolerance_without_typo()
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

        $hits = $search->json('hits.hits');

        $this->assertCount(0, $hits);
    }

    /**
     * @test
     */
    public function source()
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

        $hits = $search->json('hits.hits');

        $this->assertArrayHasKey('name', $hits[0]['_source']);
        $this->assertArrayNotHasKey('category', $hits[0]['_source']);

        $search = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('a')
            ->fields(['category'])
            ->get();

        $hits = $search->json('hits.hits');

        $this->assertArrayHasKey('name', $hits[0]['_source']);
        $this->assertArrayHasKey('category', $hits[0]['_source']);
    }

    /**
     * @test
     */
    public function highlight()
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

        $hits = $search->json('hits.hits');

        $this->assertEquals('<span class="font-bold">a</span>', $hits[0]['highlight']['category'][0]);
    }

    /**
     * @test
     */
    public function sort()
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

        $hits = $search->json('hits.hits');

        $this->assertEquals('c', $hits[0]['_source']['category']);
        $this->assertEquals('b', $hits[1]['_source']['category']);
        $this->assertEquals('a', $hits[2]['_source']['category']);
    }

    /**
     * @test
     */
    public function filter()
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
            ->filters('is:active')
            ->fields(['name', 'description'])
            ->get();

        $hits = $search->json('hits.hits');

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function size()
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

        $hits = $search->json('hits.hits');

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function query()
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

        $hits = $search->json('hits.hits');

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function matches_all_on_empty_string()
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

        $hits = $search->json('hits.hits');

        $this->assertCount(3, $hits);
    }

    /**
     * @test
     */
    public function search_test()
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
    public function hits()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
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
    public function no_keyword_search_flag()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->title('name');

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
            ->semantic()
            ->noResultsOnEmptySearch()
            ->disableKeywordSearch()
            ->properties($blueprint)
            ->queryString('Queen')
            ->get();

        $this->assertEquals(0, $response->json('hits.total.value'));

        $response = $this->sigmie
            ->newSearch($indexName)
            ->semantic()
            ->noResultsOnEmptySearch()
            ->properties($blueprint)
            ->queryString('Queen')
            ->get();

        $this->assertEquals(1, $response->json('hits.total.value'));
    }
}
