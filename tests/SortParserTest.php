<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use RuntimeException;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Parse\ParseException;
use Sigmie\Parse\SortParser;
use Sigmie\Testing\TestCase;

class SortParserTest extends TestCase
{
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
        $blueprint = new NewProperties;
        $blueprint->keyword('name');

        $props = $blueprint();
        $parser = new SortParser($props);

        $sorts = $parser->parse('_score:desc name:asc');

        $this->assertEquals([['_score' => 'desc'], ['name' => 'asc']], $sorts);
        $this->assertEmpty($parser->errors());
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
}
