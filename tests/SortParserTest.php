<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use RuntimeException;
use Sigmie\Document\Document;
use Sigmie\Index\Analysis\DefaultAnalyzer;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\ParseException;
use Sigmie\Parse\SortParser;
use Sigmie\Testing\TestCase;

class SortParserTest extends TestCase
{
    public function test_a_distance_sort_puts_a_target_index_without_that_geo_field_last(): void
    {
        $located = new NewProperties;
        $located->title('name');
        $located->geoPoint('location');

        $plain = new NewProperties;
        $plain->title('name');

        $stores = uniqid('stores_');
        $webshops = uniqid('webshops_');
        $this->sigmie->newIndex($stores)->properties($located)->create();
        $this->sigmie->newIndex($webshops)->properties($plain)->create();
        $this->sigmie->collect($stores, refresh: true)->properties($located)->merge([
            new Document(['name' => 'New York', 'location' => ['lat' => 40.71, 'lon' => -74.0]], _id: 'new-york'),
            new Document(['name' => 'Berlin', 'location' => ['lat' => 52.52, 'lon' => 13.40]], _id: 'berlin'),
        ]);
        $this->sigmie->collect($webshops, refresh: true)->properties($plain)->merge([
            new Document(['name' => 'Online'], _id: 'online'),
        ]);

        $hits = $this->sigmie->newSearch(sprintf('%s,%s', $stores, $webshops))->properties($located)
            ->queryString('')->sort('location[52.52,13.40]:km:asc')->hits();

        $this->assertSame(['berlin', 'new-york', 'online'], array_column($hits, '_id'));
    }

    public function test_geo_sort_accepts_a_decimal_point_without_fractional_digits(): void
    {
        $properties = new NewProperties;
        $properties->geoPoint('location');

        $this->assertSame(
            [['_geo_distance' => ['location' => ['lat' => 0.0, 'lon' => -0.0], 'order' => 'asc', 'unit' => 'km', 'ignore_unmapped' => true]]],
            (new SortParser($properties))->parse('location[0.,-0.E0]:km:asc')
        );
    }

    public function test_geo_sort_accepts_spaces_inside_its_coordinates(): void
    {
        $properties = new NewProperties;
        $properties->geoPoint('location');
        $properties->number('price');

        $parser = new SortParser($properties);

        $this->assertSame(
            [
                ['_geo_distance' => ['location' => ['lat' => 40.71, 'lon' => -74.0], 'order' => 'asc', 'unit' => 'km', 'ignore_unmapped' => true]],
                ['price' => 'desc'],
            ],
            $parser->parse('location[ 40.71, -74.00 ]:km:asc price:desc')
        );
    }

    /** @test */
    public function whitespace_runs_separate_sort_fields(): void
    {
        $properties = new NewProperties;
        $properties->keyword('name');
        $properties->number('price');

        $this->assertSame(
            [['name' => 'asc'], ['price' => 'desc']],
            (new SortParser($properties))->parse(" name:asc  \tprice:desc ")
        );
    }

    /** @test */
    public function a_mapped_field_named_asc_sorts_in_ascending_order(): void
    {
        $properties = new NewProperties;
        $properties->keyword('asc');

        $this->assertSame([['asc' => 'asc']], (new SortParser($properties))->parse('asc'));
    }

    /** @test */
    public function a_mapped_geo_sort_field_can_contain_a_hyphen(): void
    {
        $properties = new NewProperties;
        $properties->geoPoint('store-location');

        $this->assertSame(
            [['_geo_distance' => ['store-location' => ['lat' => 1.0, 'lon' => -2.0], 'order' => 'asc', 'unit' => 'km', 'ignore_unmapped' => true]]],
            (new SortParser($properties))->parse('store-location[1,-2]:km:asc')
        );
    }

    /** @test */
    public function geo_filters_and_sorts_accept_signed_decimals_without_a_leading_zero(): void
    {
        $indexName = uniqid('geo-decimals-');
        $properties = new NewProperties;
        $properties->geoPoint('pin');
        $this->sigmie->newIndex($indexName)->properties($properties)->create();
        $this->sigmie->collect($indexName, refresh: true)->merge([
            new Document(['pin' => ['lat' => 0.5, 'lon' => -0.25]], _id: 'near'),
            new Document(['pin' => ['lat' => 0.5, 'lon' => -0.5]], _id: 'far'),
        ]);

        $filter = (new FilterParser($properties))->parse('pin:.5km[+.5,-.25]');
        $sort = (new SortParser($properties))->parse('pin[+.5,-.25]:km:asc');
        $hits = $this->sigmie->query($indexName, $filter)->sort($sort)->get()->json('hits.hits');

        $this->assertSame(['near'], array_column($hits, '_id'));
        $this->assertLessThan(0.001, $hits[0]['sort'][0]);
    }

    public function test_field_type_audit_sorts_large_integer_ids(): void
    {
        $props = new NewProperties;
        $props->id('user_id');

        $index = uniqid('.type-sort-');
        $this->sigmie->newIndex($index)->properties($props)->create();

        try {
            $this->sigmie->collect($index, refresh: true)->properties($props)->merge([
                new Document(['user_id' => 3000000000], _id: 'large'),
                new Document(['user_id' => 7], _id: 'small'),
            ]);

            $hits = $this->sigmie->newSearch($index)->properties($props)->queryString('')
                ->sort('user_id:asc')->get()->hits();

            $this->assertSame(['small', 'large'], array_column($hits, '_id'));
        } finally {
            $this->sigmie->deleteIfExists($index);
        }
    }

    public function test_field_type_audit_rejects_direct_sort_on_geo_and_range(): void
    {
        $props = new NewProperties;
        $props->geoPoint('location');
        $props->range('availability');
        $props->date('created_at');

        $parser = new SortParser($props, throwOnError: false);

        $this->assertSame([['created_at' => 'asc']], $parser->parse('location:asc availability:asc created_at:asc'));
        $this->assertSame(['location', 'availability'], array_column($parser->errors(), 'field'));
    }

    public function test_a_restored_field_elasticsearch_cannot_sort_on_is_not_sortable(): void
    {
        $props = Properties::create([
            'area' => ['type' => 'geo_shape'],
            'body' => ['type' => 'match_only_text'],
            'blob' => ['type' => 'binary'],
            'sku' => ['type' => 'keyword', 'doc_values' => false],
            'stock' => ['type' => 'integer', 'doc_values' => false],
            'address' => ['type' => 'ip', 'doc_values' => false],
            'origin' => ['type' => 'ip'],
        ], new DefaultAnalyzer, [], Properties::ROOT_NAME);

        $parser = new SortParser($props, throwOnError: false);

        $this->assertSame([['origin' => 'asc']], $parser->parse('area:asc body:asc blob:asc sku:asc stock:asc address:asc origin:asc'));
        $this->assertSame(['area', 'body', 'blob', 'sku', 'stock', 'address'], array_column($parser->errors(), 'field'));
    }

    /**
     * @test
     */
    public function id_sort(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->id('id');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'id' => 2,
            ]),
            new Document([
                'id' => 10,
            ]),
            new Document([
                'id' => 1,
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new SortParser($props);

        $query = $parser->parse('id:desc');

        $res = $this->sigmie
            ->query($indexName)
            ->sort($query)
            ->get();

        $hits = $res->json('hits.hits');

        $this->assertTrue($hits[0]['_source']['id'] === 10);
        $this->assertTrue($hits[1]['_source']['id'] === 2);
        $this->assertTrue($hits[2]['_source']['id'] === 1);
    }

    /**
     * @test
     */
    public function non_existing_field(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->object('contact', function (NewProperties $props): void {
            $props->geoPoint('location');
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'contact' => [
                    'location' => [
                        'lat' => 52.49,
                        'lon' => 13.77,
                    ],
                ],
            ]),
            new Document([
                'contact' => [
                    'location' => [
                        'lat' => 53.49,
                        'lon' => 13.77,
                    ],
                ],
            ]),
            new Document([
                'contact' => [
                    'location' => [
                        'lat' => 54.49,
                        'lon' => 13.77,
                    ],
                ],
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new SortParser($props, false);

        $query = $parser->parse('population:desc');

        $this->assertEmpty($query);
    }

    /**
     * @test
     */
    public function object_geo_distance_sort(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('name');
        $blueprint->object('contact', function (NewProperties $props): void {
            $props->geoPoint('location');
        });

        $props = $blueprint();
        $parser = new SortParser($props);

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'name' => 'test',
                'contact' => [
                    'location' => [
                        'lat' => 52.49,
                        'lon' => 13.77,
                    ],
                ],
            ]),
            new Document([
                'name' => 'test1',
                'contact' => [
                    'location' => [
                        'lat' => 53.49,
                        'lon' => 13.77,
                    ],
                ],
            ]),
            new Document([
                'name' => 'test2',
                'contact' => [
                    'location' => [
                        'lat' => 54.49,
                        'lon' => 13.77,
                    ],
                ],
            ]),
        ];

        $index->merge($docs);

        $sort = $parser->parse('contact.location[52.49,13.77]:m:asc');

        $res = $this->sigmie
            ->query($indexName)
            ->sort($sort)
            ->get();

        $hits = $res->json('hits.hits');

        $this->assertTrue($hits[0]['_source']['contact']['location']['lat'] === 52.49);
        $this->assertTrue($hits[1]['_source']['contact']['location']['lat'] === 53.49);
        $this->assertTrue($hits[2]['_source']['contact']['location']['lat'] === 54.49);

        $sort = $parser->parse('contact.location[52.49,13.77]:m:desc');

        $res = $this->sigmie->query($indexName)
            ->sort($sort)
            ->get();

        $hits = $res->json('hits.hits');

        $this->assertTrue($hits[0]['_source']['contact']['location']['lat'] === 54.49);
        $this->assertTrue($hits[1]['_source']['contact']['location']['lat'] === 53.49);
        $this->assertTrue($hits[2]['_source']['contact']['location']['lat'] === 52.49);
    }

    /**
     * @test
     */
    public function nested_text_asc_filter(): void
    {
        $blueprint = new NewProperties;
        $blueprint->nested('contact', function (NewProperties $props): void {
            $props->bool('active');
            $props->text('name')->keyword()->makeSortable();
            $props->text('category');
        });

        $props = $blueprint();
        $parser = new SortParser($props);
        $sorts = $parser->parse('contact.name:asc');

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'contact' => [
                    'active' => true,
                    'name' => 'Pluto',
                    'category' => 'Disney',
                ],
            ]),
            new Document([
                'contact' => [
                    'active' => true,
                    'name' => 'Arthur',
                    'category' => 'Disney',
                ],
            ]),
            new Document([
                'contact' => [
                    'active' => false,
                    'name' => 'Dory',
                    'category' => 'Disney',
                ],
            ]),
            new Document([
                'contact' => [
                    'active' => false,
                    'name' => 'Dory',
                    'category' => 'Disney',
                ],
            ]),
        ];

        $index->merge($docs);

        $res = $this->sigmie->query($indexName)
            ->sort($sorts)
            ->get();

        $hits = $res->json('hits.hits');

        $this->assertTrue($hits[0]['_source']['contact']['name'] === 'Arthur');
    }

    /**
     * @test
     */
    public function geo_distance_sort_with_valid_unit(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->geoPoint('location');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'location' => [
                    'lat' => 52.49,
                    'lon' => 13.77,
                ],
            ]),
            new Document([
                'location' => [
                    'lat' => 53.49,
                    'lon' => 13.77,
                ],
            ]),
            new Document([
                'location' => [
                    'lat' => 54.49,
                    'lon' => 13.77,
                ],
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new SortParser($props);

        $this->expectException(ParseException::class);

        $parser->parse('location[52.49,13.77]:ieow:asc');

        $this->expectException(ParseException::class);

        $parser->parse('location[52.49,13.77]:km:foo');

        $this->expectException(ParseException::class);

        $parser->parse('location[foo,13.77]:km:asc');

        $this->expectException(ParseException::class);

        $parser->parse('location[91,13.77]:km:asc');
    }

    /**
     * @test
     */
    public function nested_geo_distance_sort(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->geoPoint('location');
        $blueprint->nested('contact', function (NewProperties $props): void {
            $props->geoPoint('location');
        });

        $props = $blueprint();
        $parser = new SortParser($props);

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'contact' => [
                    'location' => [
                        'lat' => 52.49,
                        'lon' => 13.77,
                    ],
                ],
            ]),
            new Document([
                'contact' => [
                    'location' => [
                        'lat' => 53.49,
                        'lon' => 13.77,
                    ],
                ],
            ]),
            new Document([
                'contact' => [
                    'location' => [
                        'lat' => 54.49,
                        'lon' => 13.77,
                    ],
                ],
            ]),
        ];

        $index->merge($docs);

        $query = $parser->parse('contact.location[52.49,13.77]:m:asc');

        $res = $this->sigmie->query($indexName)
            ->sort($query)
            ->get();

        $hits = $res->json('hits.hits');

        $this->assertTrue($hits[0]['_source']['contact']['location']['lat'] === 52.49);
        $this->assertTrue($hits[1]['_source']['contact']['location']['lat'] === 53.49);
        $this->assertTrue($hits[2]['_source']['contact']['location']['lat'] === 54.49);

        $query = $parser->parse('contact.location[52.49,13.77]:m:desc');

        $res = $this->sigmie->query($indexName)
            ->sort($query)
            ->get();

        $hits = $res->json('hits.hits');

        $this->assertTrue($hits[0]['_source']['contact']['location']['lat'] === 54.49);
        $this->assertTrue($hits[1]['_source']['contact']['location']['lat'] === 53.49);
        $this->assertTrue($hits[2]['_source']['contact']['location']['lat'] === 52.49);
    }

    /**
     * @test
     */
    public function geo_distance_sort(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->geoPoint('location');

        $props = $blueprint();
        $parser = new SortParser($props);

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'location' => [
                    'lat' => 52.49,
                    'lon' => 13.77,
                ],
            ]),
            new Document([
                'location' => [
                    'lat' => 53.49,
                    'lon' => 13.77,
                ],
            ]),
            new Document([
                'location' => [
                    'lat' => 54.49,
                    'lon' => 13.77,
                ],
            ]),
        ];

        $index->merge($docs);

        $query = $parser->parse('location[52.49,13.77]:m:asc');

        $res = $this->sigmie->query($indexName)
            ->sort($query)
            ->get();

        $hits = $res->json('hits.hits');

        $this->assertTrue($hits[0]['_source']['location']['lat'] === 52.49);
        $this->assertTrue($hits[1]['_source']['location']['lat'] === 53.49);
        $this->assertTrue($hits[2]['_source']['location']['lat'] === 54.49);

        $query = $parser->parse('location[52.49,13.77]:m:desc');

        $res = $this->sigmie->query($indexName)
            ->sort($query)
            ->get();

        $hits = $res->json('hits.hits');

        $this->assertTrue($hits[0]['_source']['location']['lat'] === 54.49);
        $this->assertTrue($hits[1]['_source']['location']['lat'] === 53.49);
        $this->assertTrue($hits[2]['_source']['location']['lat'] === 52.49);
    }

    /**
     * @test
     */
    public function exceptions(): void
    {
        new Properties;

        $blueprint = new NewProperties;

        $this->expectException(RuntimeException::class);

        $props = $blueprint();

        $parser = new SortParser($props);

        $parser->parse('name:asc _score');
    }

    /**
     * @test
     */
    public function score_desc_allowed(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('name');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, true)
            ->merge([
                new Document(['name' => 'Beta'], _id: 'beta'),
                new Document(['name' => 'Alpha'], _id: 'alpha'),
            ]);

        $props = $blueprint();
        $parser = new SortParser($props);

        $sorts = $parser->parse('_score:desc name:asc');

        $this->assertEquals([['_score' => 'desc'], ['name' => 'asc']], $sorts);
        $this->assertEmpty($parser->errors());

        $res = $this->sigmie->query($indexName)
            ->sort($sorts)
            ->get();

        $hits = $res->json('hits.hits');

        $this->assertSame(['alpha', 'beta'], array_column($hits, '_id'));
    }

    /**
     * @test
     */
    public function default_and_doc_sorts_return_elasticsearch_hits(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('name');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, true)
            ->merge([
                new Document(['name' => 'Alpha'], _id: 'alpha'),
                new Document(['name' => 'Beta'], _id: 'beta'),
            ]);

        $parser = new SortParser($blueprint());

        $scoreSorted = $this->sigmie->query($indexName)
            ->sort($parser->parse(''))
            ->get()
            ->json('hits.hits');

        $this->assertEqualsCanonicalizing(['alpha', 'beta'], array_column($scoreSorted, '_id'));

        $docSorted = $this->sigmie->query($indexName)
            ->sort($parser->parse('_doc'))
            ->get()
            ->json('hits.hits');

        $this->assertEqualsCanonicalizing(['alpha', 'beta'], array_column($docSorted, '_id'));
    }

    /**
     * @test
     */
    public function invalid_sort_tokens_collect_errors_and_keep_valid_elasticsearch_sort(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->geoPoint('location');
        $blueprint->keyword('name');
        $blueprint->number('price');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, true)
            ->merge([
                new Document([
                    'location' => ['lat' => 52.49, 'lon' => 13.77],
                    'name' => 'Gamma',
                    'price' => 10,
                ], _id: 'gamma'),
                new Document([
                    'location' => ['lat' => 53.49, 'lon' => 13.77],
                    'name' => 'Alpha',
                    'price' => 10,
                ], _id: 'alpha'),
                new Document([
                    'location' => ['lat' => 54.49, 'lon' => 13.77],
                    'name' => 'Beta',
                    'price' => 10,
                ], _id: 'beta'),
            ]);

        foreach ([
            ['price asc name:desc', ['gamma', 'beta', 'alpha'], 'attach the direction'],
            ['_score:sideways name:asc', ['alpha', 'beta', 'gamma'], 'Invalid direction'],
            ['name[52.49,13.77]:m:asc name:asc', ['alpha', 'beta', 'gamma'], 'is not a geo point'],
            ['location[52.49,13.77]:parsec:asc name:desc', ['gamma', 'beta', 'alpha'], 'Invalid unit'],
            ['location[52.49,13.77]:km:sideways name:asc', ['alpha', 'beta', 'gamma'], 'Invalid order'],
            ['location[91,13.77]:km:asc name:desc', ['gamma', 'beta', 'alpha'], 'Invalid latitude or longitude'],
        ] as [$sortString, $expectedIds, $expectedError]) {
            $parser = new SortParser($blueprint(), throwOnError: false);
            $sorts = $parser->parse($sortString);

            $hits = $this->sigmie->query($indexName)
                ->sort($sorts)
                ->get()
                ->json('hits.hits');

            $this->assertSame($expectedIds, array_column($hits, '_id'));
            $this->assertStringContainsString($expectedError, $parser->errors()[0]['message']);
        }
    }

    /**
     * @test
     */
    public function score_asc_not_allowed(): void
    {
        $blueprint = new NewProperties;
        $blueprint->keyword('name');

        $props = $blueprint();
        $parser = new SortParser($props, throwOnError: false);

        $sorts = $parser->parse('_score:asc name:asc');

        $this->assertEquals([['name' => 'asc']], $sorts);
        $this->assertNotEmpty($parser->errors());
        $this->assertStringContainsString('_score cannot be sorted in ascending order', $parser->errors()[0]['message']);
    }

    /**
     * @test
     */
    public function score_asc_without_throw_on_error(): void
    {
        $blueprint = new NewProperties;
        $blueprint->keyword('name');

        $props = $blueprint();
        $parser = new SortParser($props, throwOnError: false);

        $sorts = $parser->parse('_score:asc');

        $this->assertEmpty($sorts);
        $this->assertNotEmpty($parser->errors());
    }

    /**
     * @test
     */
    public function text_asc_filter(): void
    {
        new Properties;

        $blueprint = new NewProperties;
        $blueprint->bool('active');
        $blueprint->text('name')->keyword()->makeSortable();
        $blueprint->text('category');

        $props = $blueprint();
        $parser = new SortParser($props);
        $sorts = $parser->parse('name:asc');

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'category' => 'Disney',
                'name' => 'Pluto',
                'active' => true,
            ]),
            new Document([
                'category' => 'Disney',
                'name' => 'Arthur',
                'active' => true,
            ]),
            new Document([
                'category' => 'Disney',
                'name' => 'Dory',
                'active' => false,
            ]),
            new Document([
                'category' => 'Unknown',
                'name' => 'Zoro',
                'active' => false,
            ]),
        ];

        $index->merge($docs);

        $res = $this->sigmie->query($indexName)
            ->sort($sorts)
            ->get();

        $hits = $res->json('hits.hits');

        $this->assertTrue($hits[0]['_source']['name'] === 'Arthur');
    }

    /**
     * @test
     */
    public function text_desc_filter(): void
    {
        new Properties;

        $blueprint = new NewProperties;
        $blueprint->bool('active');
        $blueprint->text('name')->keyword()->makeSortable();
        $blueprint->text('category');

        $props = $blueprint();
        $parser = new SortParser($props);
        $sorts = $parser->parse('name:desc');

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'category' => 'Disney',
                'name' => 'Pluto',
                'active' => true,
            ]),
            new Document([
                'category' => 'Disney',
                'name' => 'Arthur',
                'active' => true,
            ]),
            new Document([
                'category' => 'Disney',
                'name' => 'Dory',
                'active' => false,
            ]),
            new Document([
                'category' => 'Unknown',
                'name' => 'Zoro',
                'active' => false,
            ]),
        ];

        $index->merge($docs);

        $res = $this->sigmie->query($indexName)
            ->sort($sorts)
            ->get();

        $hits = $res->json('hits.hits');

        $this->assertTrue($hits[0]['_source']['name'] === 'Zoro');
    }

    /**
     * @test
     */
    public function date_desc(): void
    {
        new Properties;

        $blueprint = new NewProperties;
        $blueprint->date('created_at');

        $props = $blueprint();
        $parser = new SortParser($props);

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(
                ['created_at' => '2023-04-07T12:38:29.000000Z'],
            ),
            new Document(
                ['created_at' => '2023-06-07T12:38:29.000000Z'],
            ),
            new Document(
                ['created_at' => '2023-05-07T12:38:29.000000Z'],
            ),
        ];

        $index->merge($docs);

        $sorts = $parser->parse('created_at:desc');
        $res = $this->sigmie->query($indexName)
            ->sort($sorts)
            ->get();

        $hits = $res->json('hits.hits');

        $this->assertTrue($hits[0]['_source']['created_at'] === '2023-06-07T12:38:29.000000Z');
        $this->assertTrue($hits[1]['_source']['created_at'] === '2023-05-07T12:38:29.000000Z');
        $this->assertTrue($hits[2]['_source']['created_at'] === '2023-04-07T12:38:29.000000Z');
    }

    /**
     * @test
     */
    public function date_asc(): void
    {
        new Properties;

        $blueprint = new NewProperties;
        $blueprint->date('created_at');

        $props = $blueprint();
        $parser = new SortParser($props);

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(
                ['created_at' => '2023-04-07T12:38:29.000000Z'],
            ),
            new Document(
                ['created_at' => '2023-06-07T12:38:29.000000Z'],
            ),
            new Document(
                ['created_at' => '2023-05-07T12:38:29.000000Z'],
            ),
        ];

        $index->merge($docs);

        $sorts = $parser->parse('created_at:asc');
        $res = $this->sigmie->query($indexName)
            ->addRaw('sort', $sorts)
            ->get();

        $hits = $res->json('hits.hits');

        $this->assertTrue($hits[0]['_source']['created_at'] === '2023-04-07T12:38:29.000000Z');
        $this->assertTrue($hits[1]['_source']['created_at'] === '2023-05-07T12:38:29.000000Z');
        $this->assertTrue($hits[2]['_source']['created_at'] === '2023-06-07T12:38:29.000000Z');
    }

    /**
     * @test
     */
    public function space_separated_direction_throws_a_helpful_error(): void
    {
        $blueprint = new NewProperties;
        $blueprint->number('price');

        $parser = new SortParser($blueprint());

        // SQL-style "price asc" (a space instead of "price:asc") used to throw the misleading
        // "Field asc does not exist." — now it teaches the correct colon form.
        $this->expectException(ParseException::class);
        $this->expectExceptionMessageMatches('/field:asc/');

        $parser->parse('price asc');
    }
}
