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
    public function geo_distance_sort_with_valid_unit()
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
                ]
            ]),
            new Document([
                'location' => [
                    'lat' => 53.49,
                    'lon' => 13.77,
                ]
            ]),
            new Document([
                'location' => [
                    'lat' => 54.49,
                    'lon' => 13.77,
                ]
            ])
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new SortParser($props);

        $this->expectException(ParseException::class, "Invalid unit 'ieow' for geo distance sort.");

        $query = $parser->parse('location[52.49,13.77]:ieow:asc');

        $this->expectException(ParseException::class, "Invalid order 'foo' for geo distance sort.");

        $query = $parser->parse('location[52.49,13.77]:km:foo');

        $this->expectException(ParseException::class, "Invalid latitude or longitude for geo distance sort.");

        $query = $parser->parse('location[foo,13.77]:km:asc');

        $this->expectException(ParseException::class, "Invalid latitude or longitude for geo distance sort.");

        $query = $parser->parse('location[91,13.77]:km:asc');
    }

    /**
     * @test
     */
    public function geo_distance_sort()
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
                ]
            ]),
            new Document([
                'location' => [
                    'lat' => 53.49,
                    'lon' => 13.77,
                ]
            ]),
            new Document([
                'location' => [
                    'lat' => 54.49,
                    'lon' => 13.77,
                ]
            ])
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new SortParser($props);

        $query = $parser->parse('location[52.49,13.77]:km:asc');

        $res = $this->sigmie->query($indexName)
            ->addRaw('sort', $query)
            ->get();

        $this->assertTrue($res->json('hits.hits')[0]['_source']['location']['lat'] === 52.49);
        $this->assertTrue($res->json('hits.hits')[1]['_source']['location']['lat'] === 53.49);
        $this->assertTrue($res->json('hits.hits')[2]['_source']['location']['lat'] === 54.49);

        $parser = new SortParser($props);

        $query = $parser->parse('location[52.49,13.77]:km:desc');

        $res = $this->sigmie->query($indexName)
            ->addRaw('sort', $query)
            ->get();

        $this->assertTrue($res->json('hits.hits')[0]['_source']['location']['lat'] === 54.49);
        $this->assertTrue($res->json('hits.hits')[1]['_source']['location']['lat'] === 53.49);
        $this->assertTrue($res->json('hits.hits')[2]['_source']['location']['lat'] === 52.49);
    }


    /**
     * @test
     */
    public function exceptions()
    {
        $mappings = new Properties();

        $blueprint = new NewProperties;

        $this->expectException(RuntimeException::class);

        $props = $blueprint();

        $parser = new SortParser($props);

        $sort = $parser->parse('name:asc _score');
    }

    /**
     * @test
     */
    public function text_asc_filter()
    {
        $mappings = new Properties();

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
            ->addRaw('sort', $sorts)
            ->get();

        $hits = $res->json('hits.hits');

        $this->assertTrue($hits[0]['_source']['name'] === 'Arthur');
    }

    /**
     * @test
     */
    public function text_desc_filter()
    {
        $mappings = new Properties();

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
            ->addRaw('sort', $sorts)
            ->get();

        $hits = $res->json('hits.hits');

        $this->assertTrue($hits[0]['_source']['name'] === 'Zoro');
    }

    /**
     * @test
     */
    public function date_desc()
    {
        $mappings = new Properties();

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
            ->addRaw('sort', $sorts)
            ->get();

        $hits = $res->json('hits.hits');

        $this->assertTrue($hits[0]['_source']['created_at'] === '2023-06-07T12:38:29.000000Z');
        $this->assertTrue($hits[1]['_source']['created_at'] === '2023-05-07T12:38:29.000000Z');
        $this->assertTrue($hits[2]['_source']['created_at'] === '2023-04-07T12:38:29.000000Z');
    }

    /**
     * @test
     */
    public function date_asc()
    {
        $mappings = new Properties();

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
