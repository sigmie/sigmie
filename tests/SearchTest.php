<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Http\Promise\Promise;
use Sigmie\Document\Document;
use Sigmie\Languages\English\English;
use Sigmie\Languages\German\German;
use Sigmie\Mappings\NewProperties;
use Sigmie\Query\Queries\Term\Range;
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

        $res = $this->sigmie->newSearch($indexName)
            ->properties($blueprint)
            ->queryString('Mickey', weight: 2)
            ->queryString('Goofy', weight: 1)
            ->get();

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
}
