<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use PHPUnit\Framework\ExpectationFailedException;
use RuntimeException;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\ParseException;
use Sigmie\Testing\TestCase;

class FilterParserTest extends TestCase
{
    /**
     * @test
     */
    public function parse_location_filter_with_zero_distance()
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
                    'lat' => 51.16,
                    'lon' => 13.49,
                ],
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse('location:0km[51.16,13.49]');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(0, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function parse_location_filter()
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
                    'lat' => 51.16,
                    'lon' => 13.49,
                ],
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse('location:100km[51.34,12.32]');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function location_parsing()
    {
        $filterStrings = [
            "location:70km[52.31,8.61]",
            "location:70km[52,8]",
            "location:70km[52,8.61]",
            "location:70km[52.31,8]",
            "location:100m[40.7128,-74.0060]",
            "location:5mi[-33.8688,151.2093]",
            "location:2km[48.8566,2.3522]",
            "location:500yd[35.6762,139.6503]",
            "location:1000ft[55.7558,37.6173]",
            "location:10nmi[-22.9068,-43.1729]",
            "location:50cm[-1.2921,36.8219]",
            "location:3in[41.9028,12.4964]",
        ];

        $mappings = new Properties();

        $blueprint = new NewProperties;
        $blueprint->text('name')->unstructuredText();

        $props = $blueprint();
        $parser = new FilterParser($props);

        foreach ($filterStrings as $filterString) {
            $boolean = $parser->parse($filterString);

            $this->assertTrue(true);
        }
    }

    /**
     * @test
     */
    public function has_not_filter()
    {

        $mappings = new Properties();

        $blueprint = new NewProperties;
        $blueprint->text('name')->unstructuredText();

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('NOT has:name');

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'name' => null,
            ]),
            new Document([
                'name' => 'Arthur',
            ]),
            new Document([
                'name' => 'Dory',
            ]),
        ];

        $index->merge($docs);

        $res = $this->sigmie->query($indexName, $boolean)->get();

        $hits = $res->json('hits.hits');

        $this->assertCount(1, $hits);
    }

    /**
     * @test
     */
    public function has_filter()
    {
        $mappings = new Properties();

        $blueprint = new NewProperties;
        $blueprint->text('name')->unstructuredText();

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('has:name');

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'name' => null,
            ]),
            new Document([
                'name' => 'Arthur',
            ]),
            new Document([
                'name' => 'Dory',
            ]),
        ];

        $index->merge($docs);

        $res = $this->sigmie->query($indexName, $boolean)->get();

        $hits = $res->json('hits.hits');

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function parse_exception_for_space_between_filters()
    {

        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->geoPoint('location');
        $blueprint->bool('active');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'location' => [
                    'lat' => 51.49,
                    'lon' => 13.77,
                ],
                'active' => true,
            ]),
            new Document([
                'location' => [
                    'lat' => 51.49,
                    'lon' => 13.77,
                ],
                'active' => true,
            ]),
            new Document([
                'location' => [
                    'lat' => 51.49,
                    'lon' => 13.77,
                ],
                'active' => false,
            ])
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse('location:1km[51.49,13.77] AND is:active');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(2, $res->json('hits.hits'));

        $this->expectException(ParseException::class);

        $query = $parser->parse('location:1km[51.49,13.77] is:active');
    }

    /**
     * @test
     */
    public function parse_geo_distance()
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
                    'lat' => 51.49,
                    'lon' => 13.77,
                ],
            ]),
            new Document([
                'location' => [
                    'lat' => 51.49,
                    'lon' => 13.77,
                ],
            ]),
            new Document([
                'location' => [
                    'lat' => 60.15,
                    'lon' => -164.10,
                ],
            ])
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse('location:1km[51.49,13.77]');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(2, $res->json('hits.hits'));

        $parser = new FilterParser($props);

        $query = $parser->parse('location:2000000000mi[51.49,13.77]');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(3, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function handle_empty_in()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->number('some_id')->integer();

        $props = $blueprint();

        $parser = new FilterParser($props);
        $filter = "some_id:[]";

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'some_id' => 123,
            ]),
            new Document([
                'some_id' => 456,
            ]),
            new Document([
                'some_id' => 789,
            ])
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse($filter);

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(0, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function fix_trim_in_spaces()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->number('some_id')->integer();

        $props = $blueprint();

        $parser = new FilterParser($props);
        $filter = "some_id:[' 123 ', ' 456 ', '789']";

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'some_id' => 123,
            ]),
            new Document([
                'some_id' => 456,
            ]),
            new Document([
                'some_id' => 789,
            ])
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse($filter);

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(3, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function fix_dont_replace_parenthesis_in_double_quotes()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->caseSensitiveKeyword('job_titles');
        $blueprint->caseSensitiveKeyword('industry');

        $props = $blueprint();

        $parser = new FilterParser($props);
        $filter = 'job_titles:["Chief Information Officer (CIO)"] AND industry:["Renewables & Environment"]';

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'job_titles' => 'Chief Information Officer (CIO)',
                'industry' => 'Renewables & Environment',
            ])
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse($filter);

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function fix_dont_replace_parenthesis_in_quotes()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->caseSensitiveKeyword('title');

        $props = $blueprint();

        $parser = new FilterParser($props);
        $filter = "(title:['Chief Executive Officer (CEO)'])";

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'title' => 'Chief Executive Officer (CEO)',
            ])
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse($filter);

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function fix_ignored_or()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->date('last_activity');
        $blueprint->number('account_id')->integer();
        $blueprint->number('finished_surveys_count')->integer();
        $blueprint->number('emails_click_count')->integer();

        $props = $blueprint();

        $parser = new FilterParser($props);

        $filter = "(
                        (
                            emails_click_count:'1' 
                            OR emails_click_count:'2'
                            OR emails_click_count:'3'
                        )
                     AND 
                     (
                      finished_surveys_count:'1' 
                      OR finished_surveys_count:'2' 
                      OR finished_surveys_count:'3' 
                      OR finished_surveys_count:'4' 
                      OR finished_surveys_count>='5'
                     )
                    ) 
                    AND last_activity>='2024-02-12T21:59:59.999999+00:00' 
                    AND last_activity<='2024-03-13T21:59:59.999999+00:00' 
                    AND account_id:'2'";

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'account_id' => 2,
                'last_activity' => '2024-03-01T21:59:59.999999+00:00',
                'finished_surveys_count' => 0,
                'emails_click_count' => 2,
            ])
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse($filter);

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(0, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function parse_empty_term_double_quote()
    {
        $blueprint = new NewProperties;
        $blueprint->keyword('last_activity_label');
        $blueprint->number('started_surveys_count');
        $blueprint->number('emails_sent_count');
        $blueprint->number('account_id');
        $blueprint->date('last_activity');

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse("((emails_sent_count>0) AND (last_activity_label:'smartlead_click_time')) AND last_activity>='2001-01-01T00:00:00.000000+00:00' AND last_activity<='2100-12-31T23:59:59.999999+00:00' AND account_id:'10'");

        $raw = $query->toRaw();

        $this->assertArrayHasKey('emails_sent_count', $raw['bool']['must'][0]['bool']['must'][0]['range'] ?? []);
        $this->assertArrayHasKey('last_activity_label', $raw['bool']['must'][0]['bool']['must'][1]['bool']['must'][0]['term'] ?? []);
    }

    /**
     * @test
     */
    public function parse_empty_term_double_quotes()
    {
        $blueprint = new NewProperties;
        $blueprint->keyword('database');

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse('database:""');

        // other wise we get an exception
        $this->assertTrue(true);
    }


    /**
     * @test
     */
    public function parse_empty_term()
    {
        $blueprint = new NewProperties;
        $blueprint->keyword('database');

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse("database:''");

        // other wise we get an exception
        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function parse_empty_in()
    {
        $blueprint = new NewProperties;
        $blueprint->keyword('database');

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse("database:[]");

        // other wise we get an exception
        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function parse_non_existing_parenthesis()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('database');

        $props = $blueprint();

        $parser = new FilterParser($props);

        $this->expectException(ParseException::class);

        $query = $parser->parse("(database:['GeneReviews','OMIM']) AND (mode_of_inheritance:['autosomal_dominant','autosomal_recessive'])");
    }

    /**
     * @test
     */
    public function exceptions()
    {
        $mappings = new Properties();

        $blueprint = new NewProperties;

        $props = $blueprint();

        $this->expectException(RuntimeException::class);

        $parser = new FilterParser($props);

        $boolean = $parser->parse('category:"sports" AND is:active OR name:foo');
    }

    /**
     * @test
     */
    public function foo()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('category');
        $blueprint->bool('active');
        $blueprint->number('stock')->integer();

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['category' => 'comendy', 'stock' => 10, 'active' => false]),
            new Document(['category' => 'action', 'stock' => 58, 'active' => true]),
            new Document(['category' => 'horror', 'stock' => 0, 'active' => true]),
            new Document(['category' => 'horror', 'stock' => 10, 'active' => false]),
            new Document(['category' => 'romance', 'stock' => 10, 'active' => false]),
            new Document(['category' => 'drama', 'stock' => 10, 'active' => true]),
            new Document(['category' => 'sports', 'stock' => 10, 'active' => true]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse('is:active AND NOT (category:"drama" OR category:"horror")');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(2, $res->json('hits.hits'));

        $query = $parser->parse("is:active AND NOT category:'drama'");

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(3, $res->json('hits.hits'));

        $query = $parser->parse('is:active AND stock>0');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(3, $res->json('hits.hits'));

        $query = $parser->parse('is:active');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(4, $res->json('hits.hits'));

        $query = $parser->parse('is:active AND stock>0 AND (category:"action" OR category:"horror")');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));

        $query = $parser->parse('(category:"action" OR category:"horror") AND is:active AND stock>0');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));

        $query = $parser->parse('is:active AND (category:"action" OR category:"horror") AND stock>0');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function term_long_string_filter_with_single_quotes()
    {
        $mappings = new Properties();

        $blueprint = new NewProperties;
        $blueprint->text('name')->unstructuredText()->keyword();
        $blueprint->keyword('category');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('category:\'crime & drama\' OR category:\'crime OR | AND | AND NOT sports\'');

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'category' => 'sports',
            ]),
            new Document([
                'category' => 'sports',
            ]),
            new Document([
                'category' => 'crime & drama',
            ]),
        ];

        $index->merge($docs);

        $res = $this->sigmie->query($indexName, $boolean)->get();

        foreach ($res->json('hits.hits') as $index => $data) {
            $source = $data['_source'];
            $this->assertTrue($source['category'] === 'crime & drama');
        }
    }

    /**
     * @test
     */
    public function term_long_string_filter()
    {
        $mappings = new Properties();

        $blueprint = new NewProperties;
        $blueprint->text('name')->unstructuredText()->keyword();
        $blueprint->keyword('category');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('category:"crime & drama" OR category:"crime OR | AND | AND NOT sports"');

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'category' => 'sports',
            ]),
            new Document([
                'category' => 'sports',
            ]),
            new Document([
                'category' => 'crime & drama',
            ]),
        ];

        $index->merge($docs);

        $res = $this->sigmie->query($indexName, $boolean)->get();

        foreach ($res->json('hits.hits') as $index => $data) {
            $source = $data['_source'];
            $this->assertTrue($source['category'] === 'crime & drama');
        }
    }

    /**
     * @test
     */
    public function term_filter()
    {
        $mappings = new Properties();

        $blueprint = new NewProperties;
        $blueprint->text('name')->unstructuredText()->keyword();
        $blueprint->keyword('category');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('category:"sports" AND NOT name:"Adidas"');

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'category' => 'sports',
                'name' => 'Nike',
            ]),
            new Document([
                'category' => 'sports',
                'name' => 'Adidas',
            ]),
            new Document([
                'category' => 'sports',
                'name' => 'Nike',
            ]),
        ];

        $index->merge($docs);

        $res = $this->sigmie->query($indexName, $boolean)->get();

        foreach ($res->json('hits.hits') as $index => $data) {
            $source = $data['_source'];
            $this->assertTrue($source['name'] !== 'Adidas');
            $this->assertTrue($source['category'] === 'sports');
        }
    }

    /**
     * @test
     */
    public function is_not_filter()
    {
        $mappings = new Properties();

        $blueprint = new NewProperties;
        $blueprint->bool('active');
        $blueprint->text('name')->unstructuredText();
        $blueprint->text('category');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('is_not:active');

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
        ];

        $index->merge($docs);

        $res = $this->sigmie->query($indexName, $boolean)->get();

        $hits = $res->json('hits.hits');

        $this->assertCount(1, $hits);
        $this->assertFalse($hits[0]['_source']['active']);
    }

    /**
     * @test
     */
    public function is_filter()
    {
        $mappings = new Properties();

        $blueprint = new NewProperties;
        $blueprint->bool('active');
        $blueprint->text('name')->unstructuredText();
        $blueprint->text('category');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('is:active');

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
        ];

        $index->merge($docs);

        $res = $this->sigmie->query($indexName, $boolean)->get();

        $hits = $res->json('hits.hits');

        $this->assertCount(2, $hits);
        $this->assertTrue($hits[0]['_source']['active']);
        $this->assertTrue($hits[1]['_source']['active']);
    }

    /**
     * @test
     */
    public function date_single_quotes_range()
    {
        $mappings = new Properties();

        $blueprint = new NewProperties;
        $blueprint->date('created_at');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse("created_at>='2023-05-01' AND created_at<='2023-08-01'");

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(
                ['created_at' => '2023-04-07T00:00:00.000000Z'],
            ),
            new Document(
                ['created_at' => '2023-05-07T00:00:00.000000Z'],
            ),
            new Document(
                ['created_at' => '2023-05-07T00:00:00.000000Z'],
            ),
            new Document(
                ['created_at' => '2023-09-07T00:00:00.000000Z'],
            ),
        ];

        $index->merge($docs);

        $res = $this->sigmie->query($indexName, $boolean)->get();

        $hits = $res->json('hits.hits');

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function date_double_quotes_range()
    {
        $mappings = new Properties();

        $blueprint = new NewProperties;
        $blueprint->date('created_at');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('created_at>="2023-05-01" AND created_at<="2023-08-01"');

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(
                ['created_at' => '2023-04-07T00:00:00.000000Z'],
            ),
            new Document(
                ['created_at' => '2023-05-07T00:00:00.000000Z'],
            ),
            new Document(
                ['created_at' => '2023-05-07T00:00:00.000000Z'],
            ),
            new Document(
                ['created_at' => '2023-09-07T00:00:00.000000Z'],
            ),
        ];

        $index->merge($docs);

        $res = $this->sigmie->query($indexName, $boolean)->get();

        $hits = $res->json('hits.hits');

        $this->assertCount(2, $hits);
    }

    /**
     * @test
     */
    public function not()
    {
        $mappings = new Properties();

        $blueprint = new NewProperties;
        $blueprint->category('category');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('NOT category:"Sports"');

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'category' => 'Drama',
            ]),
            new Document([
                'category' => 'Sports',
            ]),
            new Document([
                'category' => 'Action',
            ]),
        ];

        $index->merge($docs);

        $res = $this->sigmie->query($indexName, $boolean)->get();

        $hits = $res->json('hits.hits');

        $this->assertCount(2, $hits);
        $this->assertNotEquals('Sports', $hits[0]['_source']['category']);
        $this->assertNotEquals('Sports', $hits[1]['_source']['category']);
    }
}
