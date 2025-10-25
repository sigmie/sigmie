<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use RuntimeException;
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
    public function wildcard_in_query(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->searchableNumber('number');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($props)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'number' => '2353051650',
            ]),
            new Document([
                'number' => '2353051651',
            ]),
        ];

        $index->merge($docs);

        $parser = new FilterParser($props, false);

        $query = $parser->parse("number:'*650'");

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));

        $query = $parser->parse("number:'2353*'");

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(2, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function end_in_query(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->id('id');
        $props->caseSensitiveKeyword('status');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($props)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'id' => 1,
                'status' => 'in-progress',
            ]),
        ];

        $index->merge($docs);

        $parser = new FilterParser($props, false);

        $query = $parser->parse("status:'in-progress' AND id:*");

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function parse_dash(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->id('id');
        $props->caseSensitiveKeyword('status');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($props)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'id' => 1,
                'status' => 'in-progress',
            ]),
        ];

        $index->merge($docs);

        $parser = new FilterParser($props, false);

        $query = $parser->parse("status:'in-progress' AND id:*");

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function parse_spaces(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->id('id');
        $props->id('system_id');
        $props->caseSensitiveKeyword('interval');
        $props->text('order_notes');
        $props->nested('user', function (NewProperties $props): void {
            $props->id('id');
            $props->path('slug');
            $props->bool('internal');
        });
        $props->object('lead_type', function (NewProperties $props): void {
            $props->id('id');
            $props->keyword('short_code');
        });
        $props->nested('delivery_history', function (NewProperties $props): void {
            $props->id('id');
            $props->date('timestamp');
            $props->number('limit_amount')->integer();
            $props->number('requested_amount')->integer();
            $props->number('delivered_amount')->integer();
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($props)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'system_id' => 26,
                'user' => [
                    'id' => 465,
                ],
                'lead_type' => [
                    'id' => 270,
                ],
                'status' => 'active',
            ]),
        ];

        $index->merge($docs);

        $parser = new FilterParser($props, false);

        $query = $parser->parse("(user: { id:'465'} ) AND system_id:'26'");

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function parse_parentheses_filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->nested('subject_services', function (NewProperties $props): void {
            $props->id('id');
            $props->text('name');
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'subject_services' => [
                    ['name' => 'BMAT', 'id' => 23],
                    ['name' => 'IMAT', 'id' => 24],
                    ['name' => 'UCAT', 'id' => 25],
                ],
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props, false);

        $query = $parser->parse('subject_service:{id:"23"}');

        $this->sigmie->query($indexName, $query)->get();

        $this->assertNotEmpty($parser->errors());

        $query = $parser->parse('subject_services:{id:"23"}');

        $this->sigmie->query($indexName, $query)->get();

        $this->assertEmpty($parser->errors());
    }

    /**
     * @test
     */
    public function same_filter_nested_filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('name');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['name' => 'Arthur']),
            new Document(['name' => 'Dory']),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse('name:"Arthur" AND name:"Arthur"');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function parse_deep_nested_filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->nested('contact', function (NewProperties $props): void {
            $props->nested('address', function (NewProperties $props): void {
                $props->keyword('city');
                $props->keyword('marker');
            });
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'contact' => [
                    'address' => [
                        [
                            'city' => 'Be{rlin',
                            'marker' => 'X',
                        ],
                        [
                            'city' => 'Hamburg',
                            'marker' => 'A',
                        ],
                    ],
                ],
            ]),
            new Document([
                'contact' => [
                    'address' => [
                        [
                            'city' => 'Be{rlin',
                            'marker' => 'A',
                        ],
                        [
                            'city' => 'Athens',
                            'marker' => 'X',
                        ],
                    ],
                ],
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse('contact:{ address:{ city:"Be{rlin" AND marker:"X" } }');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function parse_object_filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->object('contact', function (NewProperties $props): void {
            $props->bool('active');
            $props->keyword('languages');
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'contact' => [
                    'active' => true,
                    'points' => 100,
                    'languages' => ['en', 'de'],
                    'location' => [
                        'lat' => 51.16,
                        'lon' => 13.49,
                    ],
                ],
            ]),
            new Document([
                'contact' => [
                    'active' => false,
                    'points' => 100,
                    'languages' => ['en', 'de'],
                    'location' => [
                        'lat' => 51.16,
                        'lon' => 13.49,
                    ],
                ],
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse('contact.active:true AND contact.languages:"en"');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function multi_nested_filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->nested('driver', function (NewProperties $props): void {
            $props->name('name');
            $props->nested('vehicle', function (NewProperties $props): void {
                $props->keyword('make');
                $props->keyword('model');
            });
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'driver' => [
                    'last_name' => 'McQueen',
                    'vehicle' => [
                        [
                            'make' => 'Powell Motors',
                            'model' => 'Canyonero',
                        ],
                        [
                            'make' => 'Miller-Meteor',
                            'model' => 'Ecto-1',
                        ],
                    ],
                ],
            ]),
            new Document([
                'driver' => [
                    'last_name' => 'Hudson',
                    'vehicle' => [
                        [
                            'make' => 'Mifune',
                            'model' => 'Canyonero',
                        ],
                        [
                            'make' => 'Powell Motors',
                            'model' => 'Ecto-1',
                        ],
                    ],
                ],
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse("driver.vehicle:{make:'Powell Motors' AND model:'Canyonero'}");

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function parse_nested_filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->nested('contact', function (NewProperties $props): void {
            $props->geoPoint('location');
            $props->bool('active');
            $props->number('points')->integer();
            $props->keyword('languages');
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'contact' => [
                    'active' => true,
                    'points' => 100,
                    'languages' => ['en', 'de'],
                    'location' => [
                        'lat' => 51.16,
                        'lon' => 13.49,
                    ],
                ],
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse('contact:{ active:true AND location:1km[51.16,13.49] }');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function parse_location_filter_with_zero_distance(): void
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
    public function parse_location_filter(): void
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
    public function location_parsing(): void
    {
        $filterStrings = [
            'location:70km[52.31,8.61]',
            'location:70km[52,8]',
            'location:70km[52,8.61]',
            'location:70km[52.31,8]',
            'location:100m[40.7128,-74.0060]',
            'location:5mi[-33.8688,151.2093]',
            'location:2km[48.8566,2.3522]',
            'location:500yd[35.6762,139.6503]',
            'location:1000ft[55.7558,37.6173]',
            'location:10nmi[-22.9068,-43.1729]',
            'location:50cm[-1.2921,36.8219]',
            'location:3in[41.9028,12.4964]',
        ];

        new Properties;

        $blueprint = new NewProperties;
        $blueprint->geoPoint('location');

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
    public function has_not_filter(): void
    {

        new Properties;

        $blueprint = new NewProperties;
        $blueprint->text('name')->unstructuredText();

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('NOT name:*');

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
    public function has_filter(): void
    {
        new Properties;

        $blueprint = new NewProperties;
        $blueprint->text('name')->unstructuredText();

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('name:*');

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
    public function parse_exception_for_space_between_filters(): void
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
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $query = $parser->parse('location:1km[51.49,13.77] AND active:true');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(2, $res->json('hits.hits'));

        $this->expectException(ParseException::class);

        $parser->parse('location:1km[51.49,13.77] active:true');
    }

    /**
     * @test
     */
    public function parse_geo_distance(): void
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
            ]),
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
    public function handle_empty_in(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->number('some_id')->integer();

        $props = $blueprint();

        $parser = new FilterParser($props);
        $filter = 'some_id:[]';

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
            ]),
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
    public function fix_trim_in_spaces(): void
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
            ]),
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
    public function fix_dont_replace_parenthesis_in_double_quotes(): void
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
            ]),
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
    public function fix_dont_replace_parenthesis_in_quotes(): void
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
            ]),
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
    public function fix_ignored_or(): void
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
            ]),
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
    public function parse_empty_term_double_quote(): void
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
    public function parse_empty_term_double_quotes(): void
    {
        $blueprint = new NewProperties;
        $blueprint->keyword('database');

        $props = $blueprint();

        $parser = new FilterParser($props);

        $parser->parse('database:""');

        // other wise we get an exception
        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function parse_empty_term(): void
    {
        $blueprint = new NewProperties;
        $blueprint->keyword('database');

        $props = $blueprint();

        $parser = new FilterParser($props);

        $parser->parse("database:''");

        // other wise we get an exception
        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function parse_empty_in(): void
    {
        $blueprint = new NewProperties;
        $blueprint->keyword('database');

        $props = $blueprint();

        $parser = new FilterParser($props);

        $parser->parse('database:[]');

        // other wise we get an exception
        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function parse_non_existing_parenthesis(): void
    {
        uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('database');

        $props = $blueprint();

        $parser = new FilterParser($props);

        $this->expectException(ParseException::class);

        $parser->parse("(database:['GeneReviews','OMIM']) AND (mode_of_inheritance:['autosomal_dominant','autosomal_recessive'])");
    }

    /**
     * @test
     */
    public function exceptions(): void
    {
        new Properties;

        $blueprint = new NewProperties;

        $props = $blueprint();

        $this->expectException(RuntimeException::class);

        $parser = new FilterParser($props);

        $parser->parse('category:"sports" AND active:true OR name:foo');
    }

    /**
     * @test
     */
    public function foo(): void
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

        $query = $parser->parse('active:true AND NOT (category:"drama" OR category:"horror")');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(2, $res->json('hits.hits'));

        $query = $parser->parse("active:true AND NOT category:'drama'");

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(3, $res->json('hits.hits'));

        $query = $parser->parse('active:true AND stock>0');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(3, $res->json('hits.hits'));

        $query = $parser->parse('active:true');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(4, $res->json('hits.hits'));

        $query = $parser->parse('active:true AND stock>0 AND (category:"action" OR category:"horror")');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));

        $query = $parser->parse('(category:"action" OR category:"horror") AND active:true AND stock>0');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));

        $query = $parser->parse('active:true AND (category:"action" OR category:"horror") AND stock>0');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function term_long_string_filter_with_single_quotes(): void
    {
        new Properties;

        $blueprint = new NewProperties;
        $blueprint->text('name')->unstructuredText()->keyword();
        $blueprint->keyword('category');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse("category:'crime & drama' OR category:'crime OR | AND | AND NOT sports'");

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

        foreach ($res->json('hits.hits') as $data) {
            $source = $data['_source'];
            $this->assertTrue($source['category'] === 'crime & drama');
        }
    }

    /**
     * @test
     */
    public function term_long_string_filter(): void
    {
        new Properties;

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

        foreach ($res->json('hits.hits') as $data) {
            $source = $data['_source'];
            $this->assertTrue($source['category'] === 'crime & drama');
        }
    }

    /**
     * @test
     */
    public function term_filter(): void
    {
        new Properties;

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

        foreach ($res->json('hits.hits') as $data) {
            $source = $data['_source'];
            $this->assertTrue($source['name'] !== 'Adidas');
            $this->assertTrue($source['category'] === 'sports');
        }
    }

    /**
     * @test
     */
    public function is_not_filter(): void
    {
        new Properties;

        $blueprint = new NewProperties;
        $blueprint->bool('active');
        $blueprint->text('name')->unstructuredText();
        $blueprint->text('category');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('active:false');

        $indexName = uniqid();
        $index = $this->sigmie
            /**
             * @test
             */
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
    public function is_filter(): void
    {
        new Properties;

        $blueprint = new NewProperties;
        $blueprint->bool('active');
        $blueprint->text('name')->unstructuredText();
        $blueprint->text('category');

        $props = $blueprint();
        $parser = new FilterParser($props);
        $boolean = $parser->parse('active:true');

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
    public function date_single_quotes_range(): void
    {
        new Properties;

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
    public function date_double_quotes_range(): void
    {
        new Properties;

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
    public function not(): void
    {
        new Properties;

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

    /**
     * @test
     */
    public function nested_object_is_active_filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->object('contact', function (NewProperties $props): void {
            $props->bool('is_active');
            $props->text('name')->unstructuredText();
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'contact' => [
                    'is_active' => true,
                    'name' => 'John Doe',
                ],
            ]),
            new Document([
                'contact' => [
                    'is_active' => false,
                    'name' => 'Jane Doe',
                ],
            ]),
            new Document([
                'contact' => [
                    'is_active' => true,
                    'name' => 'Alice',
                ],
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();
        $parser = new FilterParser($props);

        $query = $parser->parse('contact.is_active:true');

        $res = $this->sigmie->query($indexName, $query)->get();

        $hits = $res->json('hits.hits');

        $this->assertCount(2, $hits);
        $this->assertTrue($hits[0]['_source']['contact']['is_active']);
        $this->assertTrue($hits[1]['_source']['contact']['is_active']);
    }

    /**
     * @test
     */
    public function nested_object_is_not_active_filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->object('contact', function (NewProperties $props): void {
            $props->bool('is_active');
            $props->text('name')->unstructuredText();
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'contact' => [
                    'is_active' => true,
                    'name' => 'John Doe',
                ],
            ]),
            new Document([
                'contact' => [
                    'is_active' => false,
                    'name' => 'Jane Doe',
                ],
            ]),
            new Document([
                'contact' => [
                    'is_active' => true,
                    'name' => 'Alice',
                ],
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();
        $parser = new FilterParser($props);

        $query = $parser->parse('contact.is_active:false');

        $res = $this->sigmie->query($indexName, $query)->get();

        $hits = $res->json('hits.hits');

        $this->assertCount(1, $hits);
        $this->assertFalse($hits[0]['_source']['contact']['is_active']);
    }

    /**
     * @test
     */
    public function nested_object_name_filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->object('contact', function (NewProperties $props): void {
            $props->bool('is_active');
            $props->name('name');
            $props->address();
            $props->caseSensitiveKeyword('code');
            $props->category();
            $props->date('created_at');
            $props->email();
            $props->geoPoint('location');
            $props->searchableNumber('searchable_number');
            $props->title('title');
            $props->longText('long_text');
            $props->number('number');
            $props->tags('tags');
            $props->price('price');
            $props->html('html');
            $props->bool('is_active');
            $props->id('id');
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'contact' => [
                    'name' => 'John Doe',
                    'address' => '123 Main St',
                    'code' => 'A1B2C3',
                    'category' => 'Employee',
                    'created_at' => '2023-09-07T00:00:00.000000Z',
                    'email' => 'john.doe@example.com',
                    'location' => [
                        'lat' => 40.7128,
                        'lon' => -74.0060,
                    ],
                    'searchable_number' => 12345,
                    'title' => 'Mr.',
                    'long_text' => 'This is a long text field.',
                    'number' => 42,
                    'tags' => ['tag1', 'tag2'],
                    'price' => 99.99,
                    'html' => '<p>Some HTML content</p>',
                    'is_active' => true,
                    'id' => '1',
                ],
            ]),
            new Document([
                'contact' => [
                    'is_active' => false,
                    'name' => 'Jane Doe',
                    'address' => '456 Elm St',
                    'code' => 'D4E5F6',
                    'category' => 'Manager',
                    'created_at' => '2023-09-08T00:00:00.000000Z',
                    'email' => 'jane.doe@example.com',
                    'location' => [
                        'lat' => 34.0522,
                        'lon' => -118.2437,
                    ],
                    'searchable_number' => 67890,
                    'title' => 'Ms.',
                    'long_text' => 'This is another long text field.',
                    'number' => 84,
                    'tags' => ['tag3', 'tag4'],
                    'price' => 199.99,
                    'html' => '<p>Another HTML content</p>',
                    'id' => '2',
                ],
            ]),
            new Document([
                'contact' => [
                    'is_active' => true,
                    'name' => 'Alice',
                    'address' => '789 Oak St',
                    'code' => 'G7H8I9',
                    'category' => 'Intern',
                    'created_at' => '2023-09-09T00:00:00.000000Z',
                    'email' => 'alice@example.com',
                    'location' => [
                        'lat' => 37.7749,
                        'lon' => -122.4194,
                    ],
                    'searchable_number' => 54321,
                    'title' => 'Ms.',
                    'long_text' => 'This is yet another long text field.',
                    'number' => 21,
                    'tags' => ['tag5', 'tag6'],
                    'price' => 299.99,
                    'html' => '<p>Yet another HTML content</p>',
                    'id' => '3',
                ],
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();
        $parser = new FilterParser($props);

        $query = $parser->parse('contact.is_active:true AND contact.name:"Alice" AND contact.address:"789 Oak St" AND contact.code:"G7H8I9" AND contact.category:"Intern" AND contact.email:"alice@example.com" AND contact.searchable_number:\'54321\' AND contact.title:"Ms." AND contact.number:\'21\' AND contact.price:\'299.99\' AND contact.id:"3"');

        $res = $this->sigmie->query($indexName, $query)->get();

        $hits = $res->json('hits.hits');

        $this->assertCount(1, $hits);
        $this->assertEquals(true, $hits[0]['_source']['contact']['is_active']);
        $this->assertEquals('Alice', $hits[0]['_source']['contact']['name']);
        $this->assertEquals('789 Oak St', $hits[0]['_source']['contact']['address']);
        $this->assertEquals('G7H8I9', $hits[0]['_source']['contact']['code']);
        $this->assertEquals('Intern', $hits[0]['_source']['contact']['category']);
        $this->assertEquals('2023-09-09T00:00:00.000000Z', $hits[0]['_source']['contact']['created_at']);
        $this->assertEquals('alice@example.com', $hits[0]['_source']['contact']['email']);
        $this->assertEquals(37.7749, $hits[0]['_source']['contact']['location']['lat']);
        $this->assertEquals(-122.4194, $hits[0]['_source']['contact']['location']['lon']);
        $this->assertEquals(54321, $hits[0]['_source']['contact']['searchable_number']);
        $this->assertEquals('Ms.', $hits[0]['_source']['contact']['title']);
        $this->assertEquals('This is yet another long text field.', $hits[0]['_source']['contact']['long_text']);
        $this->assertEquals(21, $hits[0]['_source']['contact']['number']);
        $this->assertEquals(['tag5', 'tag6'], $hits[0]['_source']['contact']['tags']);
        $this->assertEquals(299.99, $hits[0]['_source']['contact']['price']);
        $this->assertEquals('<p>Yet another HTML content</p>', $hits[0]['_source']['contact']['html']);
        $this->assertEquals('3', $hits[0]['_source']['contact']['id']);
    }

    /**
     * @test
     */
    public function max_nested_filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->number('some_number');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'some_number' => 10,
            ]),
            new Document([
                'some_number' => 20,
            ]),
        ];

        $index->merge($docs);

        $props = $blueprint();
        $parser = new FilterParser($props);

        $filter = '';
        $filter .= '(((((((((((((((((NOT some_number:"20" AND NOT some_number:"10")';
        $filter .= ' AND (some_number>"5" OR some_number<"15"))';
        $filter .= ' AND NOT some_number:"12")';
        $filter .= ' OR (some_number>="8" AND some_number<="25"))';
        $filter .= ' OR (NOT some_number:"18" AND some_number>"0"))';
        $filter .= ' OR (some_number<"30" AND NOT some_number:"22"))';
        $filter .= ' OR (some_number>="1" AND some_number<="50")))))))))))))))))';

        $this->expectException(ParseException::class);

        $query = $parser->parse($filter);

        $this->sigmie->query($indexName, $query)->get();
    }

    /**
     * @test
     */
    public function handle_between(): void
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->number('price');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($props)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'price' => 100,
            ]),
            new Document([
                'price' => 150,
            ]),
            new Document([
                'price' => 200,
            ]),
        ];

        $index->merge($docs);

        $parser = new FilterParser($props, false);

        $query = $parser->parse('price:100..180');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(2, $res->json('hits.hits'));

        $query = $parser->parse('price:100..200');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(3, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function handle_between_with_dates(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->date('last_activity');

        $props = $blueprint();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document([
                'last_activity' => '2001-01-01T00:00:00.000000+00:00',
            ]),
            new Document([
                'last_activity' => '2100-12-31T23:59:59.999999+00:00',
            ]),
            new Document([
                'last_activity' => '2001-01-01T00:00:00.000000+00:00',
            ]),
        ];

        $index->merge($docs);

        $parser = new FilterParser($props);

        $query = $parser->parse('last_activity:2001-01-01T00:00:00.000000+00:00..2100-12-31T23:59:59.999999+00:00');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(3, $res->json('hits.hits'));

        $query = $parser->parse('last_activity:2090-01-01T00:00:00.000000+00:00..2100-12-31T23:59:59.999999+00:00');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(1, $res->json('hits.hits'));
    }

    /**
     * @test
     */
    public function handle_numbers_without_quotes(): void
    {
        new Properties;

        $blueprint = new NewProperties;
        $blueprint->price();

        $indexName = uniqid();
        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(
                ['price' => 100],
            ),
            new Document(
                ['price' => 150],
            ),
            new Document(
                ['price' => 200],
            ),
            new Document(
                ['price' => 250],
            ),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $boolean = $parser->parse('price:100');

        $res = $this->sigmie->query($indexName, $boolean)->get();

        $hits = $res->json('hits.hits');

        $this->assertCount(1, $hits);
    }

    /**
     * @test
     */
    public function filter_syntax_exception(): void
    {
        new Properties;

        $blueprint = new NewProperties;
        $blueprint->category('color');
        $blueprint->number('stock');

        $indexName = uniqid();

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(
                [
                    'color' => 'red',
                    'stock' => 100,
                ],
            ),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        $this->expectException(ParseException::class);

        $parser->parse("color:'red' color:'blue'");
    }

    /**
     * @test
     */
    public function trim_quotes_from_id_filter(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['title' => 'Doc 1'], _id: 'abc123'),
            new Document(['title' => 'Doc 2'], _id: 'def456'),
            new Document(['title' => 'Doc 3'], _id: 'ghi789'),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FilterParser($props);

        // Test with single quotes
        $query = $parser->parse("_id:['abc123','def456']");

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(2, $res->json('hits.hits'));

        // Test with double quotes
        $query = $parser->parse('_id:["abc123","ghi789"]');

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(2, $res->json('hits.hits'));

        // Test with mixed quotes
        $query = $parser->parse("_id:['abc123',\"def456\"]");

        $res = $this->sigmie->query($indexName, $query)->get();

        $this->assertCount(2, $res->json('hits.hits'));
    }
}
