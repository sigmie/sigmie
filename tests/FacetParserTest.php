<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use ReflectionMethod;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Parse\FacetParser;
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\ParseException;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Testing\TestCase;

class FacetParserTest extends TestCase
{
    public function test_lenient_facets_discard_invalid_selections_before_searching(): void
    {
        $index = uniqid('.facet-selection-');
        $properties = new NewProperties;
        $properties->keyword('color');
        $properties->text('description');
        $this->sigmie->newIndex($index)->properties($properties)->create();

        try {
            $this->sigmie->collect($index, refresh: true)->merge([
                new Document(['color' => 'green'], 'green'),
                new Document(['color' => 'red'], 'red'),
            ]);

            $response = $this->sigmie->newSearch($index)->properties($properties)
                ->facets('color', "color:'green' description:'plain text'")->get();

            $this->assertSame(['green'], array_column($response->hits(), '_id'));
            $this->assertEquals(['green' => 1, 'red' => 1], $response->facet('color'));
            $this->assertSame('description', $response->json('errors.0.field'));
        } finally {
            $this->sigmie->deleteIfExists($index);
        }
    }

    public function test_term_facet_parameters_are_validated_before_search(): void
    {
        $properties = new NewProperties;
        $properties->keyword('brand');
        $properties->caseSensitiveKeyword('code');
        $properties->text('label')->keyword();

        $parser = new FacetParser($properties, throwOnError: false);
        $facets = $parser->parse('brand:0 code:2,sideways label:2,asc,desc label:2,desc')->toRaw();

        $this->assertSame(['brand', 'code', 'label'], array_column($parser->errors(), 'field'));
        $this->assertSame(['label'], array_keys($facets));
        $this->assertSame(['_key' => 'desc'], $facets['label']['aggs']['label']['terms']['order']);
    }

    public function test_a_skipped_facet_is_absent_from_the_response(): void
    {
        $properties = new NewProperties;
        $properties->price();
        $properties->keyword('brand');
        $index = uniqid('invalid-price-facet-');
        $this->sigmie->newIndex($index)->properties($properties)->create();
        $this->sigmie->collect($index, true)->merge([new Document(['price' => 10, 'brand' => 'acme'])]);

        $response = $this->sigmie->newSearch($index)->properties($properties)->facets('price:0 brand')->get();

        $this->assertSame(['brand' => ['acme' => 1]], $response->json('facets'));
        $this->assertNull($response->facet('price'));
        $this->assertSame('price', $response->json('errors')[0]['field']);
    }

    public function test_a_blank_id_does_not_discard_other_ids_in_a_facet_filter(): void
    {
        $properties = new NewProperties;
        $properties->keyword('brand');
        $index = uniqid('id-facet-');
        $this->sigmie->newIndex($index)->properties($properties)->create();
        $this->sigmie->collect($index, true)->merge([
            new Document(['brand' => 'acme'], _id: '0'),
            new Document(['brand' => 'other'], _id: 'other'),
        ]);

        $response = $this->sigmie->newSearch($index)->properties($properties)
            ->facets('brand', '_id:["", "0"]')->get();

        $this->assertSame(['0'], array_column($response->hits(), '_id'));
        $this->assertSame(['acme' => 1], $response->facet('brand'));
    }

    public function test_a_compound_nested_selection_filters_each_level_of_a_facet(): void
    {
        $properties = new NewProperties;
        $properties->nested('orders', function (NewProperties $orders): void {
            $orders->keyword('status');
            $orders->nested('items', function (NewProperties $items): void {
                $items->number('quantity');
                $items->keyword('color');
            });
        });
        $index = uniqid('compound-nested-facet-');
        $this->sigmie->newIndex($index)->properties($properties)->create();
        $this->sigmie->collect($index, true)->merge([
            new Document(['orders' => [
                ['status' => 'open', 'items' => [
                    ['quantity' => 1, 'color' => 'red'],
                    ['quantity' => 2, 'color' => 'blue'],
                ]],
                ['status' => 'closed', 'items' => [['quantity' => 1, 'color' => 'green']]],
            ]]),
        ]);

        $response = $this->sigmie->newSearch($index)->properties($properties)
            ->facets('orders.items.color', 'orders:{ status:open AND items:{ quantity:1 } }')->get();

        $this->assertSame(['red' => 1], $response->facet('orders.items.color'));
    }

    public function test_qualified_nested_facet_selections_group_by_the_mapped_field(): void
    {
        $properties = new NewProperties;
        $properties->nested('items', fn (NewProperties $items) => $items->keyword('color')->facetDisjunctive());
        $name = uniqid('.qualified-nested-facet-');
        $index = $this->sigmie->newIndex($name)->properties($properties)->create();
        try {
            $this->sigmie->collect($name, true)->merge([
                new Document(['items' => [['color' => 'blue']]], 'blue'),
                new Document(['items' => [['color' => 'red']]], 'red'),
                new Document(['items' => [['color' => 'green']]], 'green'),
            ]);
            $response = $this->sigmie->newSearch($name)->properties($properties)->queryString('')
                ->facets('items.color', 'items:{items.color:red} items:{color:blue}', throwOnError: true)->get();

            $this->assertCount(2, $response->hits());
        } finally {
            $index->delete();
        }
    }

    public function test_a_negated_nested_selection_excludes_the_parent_from_other_facets(): void
    {
        $properties = new NewProperties;
        $properties->nested('items', function (NewProperties $items): void {
            $items->keyword('color');
            $items->number('qty');
        });

        $index = uniqid('negated-items-');
        $this->sigmie->newIndex($index)->properties($properties)->create();
        $this->sigmie->collect($index, true)->merge([
            new Document(['items' => [['color' => 'blue', 'qty' => 1], ['color' => 'red', 'qty' => 2]]]),
            new Document(['items' => [['color' => 'red', 'qty' => 3]]]),
        ]);

        $search = $this->sigmie->newSearch($index)->properties($properties)->queryString('');
        $response = $search->facets('items.qty', 'NOT items:{color:blue}')->get();

        $this->assertEquals(3, $response->facet('items.qty')['sum']);
    }

    public function test_a_negated_nested_selection_leaves_its_own_facet_unfiltered(): void
    {
        $properties = new NewProperties;
        $properties->nested('items', fn (NewProperties $items) => $items->keyword('color'));
        $index = uniqid('all-item-colors-');
        $this->sigmie->newIndex($index)->properties($properties)->create();
        $this->sigmie->collect($index, true)->merge([new Document(['items' => [['color' => 'blue']]])]);

        $search = $this->sigmie->newSearch($index)->properties($properties)->queryString('');
        $response = $search->facets('items.color', 'NOT items:{color:blue}')->get();

        $this->assertSame(['blue' => 1], $response->facet('items.color'));
    }

    /** @test @withoutCluster */
    public function quoted_parentheses_after_an_escaped_quote_are_facet_values(): void
    {
        $properties = new NewProperties;
        $properties->keyword('author');
        $filter = <<<'FILTER'
            author:"She said \"Hi (again\""
            FILTER;

        $this->assertSame('('.$filter.')', (new FacetParser($properties))->parseFilterString($filter));
    }

    /** @test @withoutCluster */
    public function a_facet_filter_can_group_clauses_on_a_hyphenated_field(): void
    {
        $properties = new NewProperties;
        $properties->keyword('sales-region')->facetDisjunctive();

        $this->assertSame(
            "(sales-region:'east' OR sales-region:'west')",
            (new FacetParser($properties))->parseFilterString("sales-region:'east' sales-region:'west'")
        );
    }

    /** @test @withoutCluster */
    public function the_facet_filter_accepts_whitespace_around_the_colon(): void
    {
        $properties = new NewProperties;
        $properties->keyword('color');
        $properties->keyword('author');

        $this->assertSame(
            "(color:'red') AND (author:'A : B')",
            (new FacetParser($properties))->parseFilterString("color : 'red' author: 'A : B'")
        );
    }

    /** @test @withoutCluster */
    public function the_facet_filter_accepts_the_builtin_id_field_without_a_mapping(): void
    {
        $properties = new NewProperties;
        $properties->keyword('color');

        $grouped = (new FacetParser($properties))->parseFilterString("_id:['doc-7'] color:'red'");
        $body = (new FilterParser($properties))->parse($grouped)->toRaw();

        $this->assertSame(['doc-7'], $body['bool']['must'][0]['bool']['must'][0]['ids']['values']);
        $this->assertSame('red', $body['bool']['must'][1]['bool']['must'][0]['term']['color']['value']);
    }

    /**
     * @test
     */
    public function no_existing_field(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->caseSensitiveKeyword('name');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['name' => '1']),
            new Document(['name' => '1.1']),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FacetParser($props, throwOnError: false);

        $parser->parse('nameabc:2,asc');

        $this->assertNotEmpty($parser->errors());
    }

    /**
     * @test
     */
    public function case_sensitive_keyword(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->caseSensitiveKeyword('name');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['name' => '1']),
            new Document(['name' => '1.1']),
            new Document(['name' => '11']),
            new Document(['name' => '1.1/2']),
            new Document(['name' => 'a']),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FacetParser($props);

        $aggs = $parser->parse('name:2,asc');

        $res = $this->sigmie->query($indexName, new MatchAll, $aggs)->get();

        $json = $res->json();

        $this->assertEquals('1', $json['aggregations']['name']['name']['buckets'][0]['key']);
        $this->assertEquals('1.1', $json['aggregations']['name']['name']['buckets'][1]['key']);
        $this->assertNull($json['aggregations']['name']['name']['buckets'][2]['key'] ?? null);
        $this->assertNull($json['aggregations']['name']['name']['buckets'][3]['key'] ?? null);
    }

    /**
     * @test
     */
    public function sort_numbers(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('name');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['name' => '1']),
            new Document(['name' => '1.1']),
            new Document(['name' => '11']),
            new Document(['name' => '1.1/2']),
            new Document(['name' => 'a']),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FacetParser($props);

        $aggs = $parser->parse('name:2,asc');

        $res = $this->sigmie->query($indexName, new MatchAll, $aggs)->get();

        $json = $res->json();

        $this->assertEquals('1', $json['aggregations']['name']['name']['buckets'][0]['key']);
        $this->assertEquals('1.1', $json['aggregations']['name']['name']['buckets'][1]['key']);
        $this->assertNull($json['aggregations']['name']['name']['buckets'][2]['key'] ?? null);
        $this->assertNull($json['aggregations']['name']['name']['buckets'][3]['key'] ?? null);
    }

    /**
     * @test
     */
    public function terms_sort(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('name');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, true);

        $docs = [
            new Document(['name' => 'a']),
            new Document(['name' => 'z']),
            new Document(['name' => 'b']),
            new Document(['name' => 'c']),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FacetParser($props);

        $aggs = $parser->parse('name:2,asc');

        $res = $this->sigmie->query($indexName, new MatchAll, $aggs)->get();

        $json = $res->json();

        $this->assertEquals('a', $json['aggregations']['name']['name']['buckets'][0]['key']);
        $this->assertEquals('b', $json['aggregations']['name']['name']['buckets'][1]['key']);

        $aggs = $parser->parse('name:2,desc');

        $res = $this->sigmie->query($indexName, new MatchAll, $aggs)->get();

        $json = $res->json();

        $this->assertEquals('z', $json['aggregations']['name']['name']['buckets'][0]['key']);
        $this->assertEquals('c', $json['aggregations']['name']['name']['buckets'][1]['key']);

        $aggs = $parser->parse('name:1,desc');

        $res = $this->sigmie->query($indexName, new MatchAll, $aggs)->get();

        $json = $res->json();

        $this->assertEquals('z', $json['aggregations']['name']['name']['buckets'][0]['key']);
        $this->assertCount(1, $json['aggregations']['name']['name']['buckets']);
    }

    /**
     * @test
     */
    public function exception(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->longText('description');
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
            new Document(['category' => 'romance', 'stock' => 1, 'active' => false]),
            new Document(['category' => 'drama', 'stock' => 10, 'active' => true]),
            new Document(['category' => 'sports', 'stock' => 10, 'active' => true]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FacetParser($props);

        $this->expectException(ParseException::class);

        $aggs = $parser->parse('category description stock active');

        $this->sigmie->query($indexName, new MatchAll, $aggs)->get();
    }

    /**
     * @test
     */
    public function has_aggregations(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->longText('description');
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
            new Document(['category' => 'romance', 'stock' => 1, 'active' => false]),
            new Document(['category' => 'drama', 'stock' => 10, 'active' => true]),
            new Document(['category' => 'sports', 'stock' => 10, 'active' => true]),
        ];

        $index->merge($docs);

        $props = $blueprint();

        $parser = new FacetParser($props);

        $aggs = $parser->parse('category:2 stock');

        $res = $this->sigmie->query($indexName, new MatchAll, $aggs)->get();

        $json = $res->json();

        $this->assertCount(2, $json['aggregations']['category']['category']['buckets']);
        $this->assertArrayHasKey('aggregations', $json);
        $this->assertArrayHasKey('category', $json['aggregations']);
        $this->assertArrayHasKey('stock', $json['aggregations']);
    }

    /**
     * @test
     */
    public function parse_filter_string_scopes_elasticsearch_facet_aggregations(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->caseSensitiveKeyword('category');
        $blueprint->caseSensitiveKeyword('type');

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, true)->merge([
            new Document(['category' => 'books', 'type' => 'guide']),
            new Document(['category' => 'books', 'type' => 'reference']),
            new Document(['category' => 'music', 'type' => 'guide']),
            new Document(['category' => 'music', 'type' => 'reference']),
        ]);

        $parser = new FacetParser($blueprint());
        $facetFilter = $parser->parseFilterString("category:'books' type:'guide'");
        $aggs = $parser->parse('category type', $facetFilter);

        $res = $this->sigmie->query($indexName, new MatchAll, $aggs)->get();

        $categoryBuckets = array_column($res->json('aggregations.category.category.buckets'), 'doc_count', 'key');
        $typeBuckets = array_column($res->json('aggregations.type.type.buckets'), 'doc_count', 'key');
        ksort($categoryBuckets);
        ksort($typeBuckets);

        $this->assertSame([
            'books' => 1,
            'music' => 1,
        ], $categoryBuckets);
        $this->assertSame([
            'guide' => 1,
            'reference' => 1,
        ], $typeBuckets);
    }

    protected function setUp(): void
    {
        if ($this->withoutCluster()) {
            return;
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        if ($this->withoutCluster()) {
            return;
        }

        parent::tearDown();
    }

    private function withoutCluster(): bool
    {
        $comment = (new ReflectionMethod($this, $this->getName(false)))->getDocComment();

        return is_string($comment) && str_contains($comment, '@withoutCluster');
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function searchable_and_plain_text_fields_are_grouped_once(): void
    {
        (new FacetParserCorrectnessTest)->searchable_and_plain_text_fields_are_grouped_once();
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function greater_than_clauses_group_on_the_field(): void
    {
        (new FacetParserCorrectnessTest)->greater_than_clauses_group_on_the_field();
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function quoted_spaces_stay_inside_one_facet_clause(): void
    {
        (new FacetParserCorrectnessTest)->quoted_spaces_stay_inside_one_facet_clause();
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function parse_filter_string_clears_the_previous_error(): void
    {
        (new FacetParserCorrectnessTest)->parse_filter_string_clears_the_previous_error();
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function lowercase_operators_are_rejected_and_a_value_named_or_stays(): void
    {
        (new FacetParserCorrectnessTest)->lowercase_operators_are_rejected_and_a_value_named_or_stays();
    }

    /**
     * @test
     *
     * @withoutCluster
     */
    public function a_nested_clause_and_a_not_clause_stay_whole(): void
    {
        (new FacetParserCorrectnessTest)->a_nested_clause_and_a_not_clause_stay_whole();
    }
}

class FacetParserCorrectnessTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @test
     */
    public function searchable_and_plain_text_fields_are_grouped_once(): void
    {
        $properties = new NewProperties;
        $properties->category('category')->facetDisjunctive();
        $properties->text('author')->facetSearchable();
        $properties->text('title');

        $parser = new FacetParser($properties);

        $this->assertSame(
            "(category:'books') AND (author:'Jane')",
            $parser->parseFilterString("category:'books' author:'Jane'"),
        );
        $this->assertSame(
            "(author:'Jane') AND (category:'books')",
            $parser->parseFilterString("author:'Jane' category:'books'"),
        );
        $this->assertSame(
            "(category:'books') AND (title:'Guide')",
            $parser->parseFilterString("category:'books' title:'Guide'"),
        );
        $this->assertSame(
            "(author:'Jane' AND author:'Doe')",
            $parser->parseFilterString("author:'Jane' author:'Doe'"),
        );
        $this->assertSame(
            "(category:'books' OR category:'music') AND (author:'Jane')",
            $parser->parseFilterString("category:'books' category:'music' author:'Jane'"),
        );
    }

    /**
     * @test
     */
    public function greater_than_clauses_group_on_the_field(): void
    {
        $properties = new NewProperties;
        $properties->number('price')->facetDisjunctive();
        $properties->number('stock');
        $properties->keyword('color');

        $parser = new FacetParser($properties);

        $this->assertSame('(price>100)', $parser->parseFilterString('price>100'));
        $this->assertSame('(price>=100)', $parser->parseFilterString('price>=100'));
        $this->assertSame('(stock>0)', $parser->parseFilterString('stock>0'));
        $this->assertSame(
            "(color:'red') AND (price>100)",
            $parser->parseFilterString("color:'red' price>100"),
        );
        $this->assertSame(
            '(price<100 OR price<=100 OR price>100 OR price>=100)',
            $parser->parseFilterString('price<100 price<=100 price>100 price>=100'),
        );
    }

    /**
     * @test
     */
    public function quoted_spaces_stay_inside_one_facet_clause(): void
    {
        $properties = new NewProperties;
        $properties->keyword('author');
        $properties->keyword('category');

        $parser = new FacetParser($properties);
        $grouped = $parser->parseFilterString("author:'John Doe'");

        $this->assertSame("(author:'John Doe')", $grouped);
        $this->assertSame(
            "(author:'John Doe') AND (category:'sci fi')",
            $parser->parseFilterString("author:'John Doe' category:'sci fi'"),
        );
        $this->assertSame(
            ['author', 'category'],
            $parser->fields("author:'John Doe' category:'books'"),
        );

        $parsed = (new FilterParser($properties))->parse($grouped)->toRaw();

        $this->assertSame(
            'John Doe',
            $parsed['bool']['must'][0]['bool']['must'][0]['term']['author']['value'],
        );
    }

    /**
     * @test
     */
    public function parse_filter_string_clears_the_previous_error(): void
    {
        $properties = new NewProperties;
        $properties->keyword('color');

        $parser = new FacetParser($properties, throwOnError: false);

        $this->assertSame('', $parser->parseFilterString("missing:'x'"));
        $this->assertSame([
            [
                'message' => "Facet field 'missing' was not found.",
                'field' => 'missing',
            ],
        ], $parser->errors());

        $this->assertSame("(color:'red')", $parser->parseFilterString("color:'red'"));
        $this->assertSame([], $parser->errors());

        $this->assertSame('', $parser->parseFilterString("color:'red' AND color:'blue'"));
        $this->assertSame([
            [
                'message' => "Facet filter string cannot contain logical operators (AND, OR, AND NOT): 'color:'red' AND color:'blue''",
            ],
        ], $parser->errors());
    }

    /**
     * @test
     */
    public function lowercase_operators_are_rejected_and_a_value_named_or_stays(): void
    {
        $properties = new NewProperties;
        $properties->keyword('color');

        $parser = new FacetParser($properties, throwOnError: false);
        $message = "Facet filter string cannot contain logical operators (AND, OR, AND NOT): '%s'";

        $this->assertSame('', $parser->parseFilterString("color:'red' or color:'blue'"));
        $this->assertSame([
            ['message' => sprintf($message, "color:'red' or color:'blue'")],
        ], $parser->errors());

        $this->assertSame('', $parser->parseFilterString("color:'red' and not color:'blue'"));
        $this->assertSame([
            ['message' => sprintf($message, "color:'red' and not color:'blue'")],
        ], $parser->errors());

        $this->assertSame('(color:OR)', $parser->parseFilterString('color:OR'));
        $this->assertSame([], $parser->errors());
        $this->assertSame('(color:NOT)', $parser->parseFilterString('color:NOT'));

        $throwing = new FacetParser($properties, throwOnError: true);

        try {
            $throwing->parseFilterString("color:'red' or color:'blue'");
            $this->fail('A lowercase OR must throw the logical-operator error.');
        } catch (ParseException $exception) {
            $this->assertSame(
                sprintf($message, "color:'red' or color:'blue'"),
                $exception->getMessage(),
            );
        }
    }

    /**
     * @test
     */
    public function a_nested_clause_and_a_not_clause_stay_whole(): void
    {
        $properties = new NewProperties;
        $properties->keyword('color');
        $properties->nested('contact', function (NewProperties $contact): void {
            $contact->keyword('city');
        });

        $parser = new FacetParser($properties, throwOnError: false);

        $nested = $parser->parseFilterString("contact:{ city:'Berlin' }");
        $this->assertSame("(contact:{ city:'Berlin' })", $nested);
        $this->assertSame([], $parser->errors());

        $nestedQuery = (new FilterParser($properties))->parse($nested)->toRaw();
        $this->assertSame(
            'Berlin',
            $nestedQuery['bool']['must'][0]['bool']['must'][0]['nested']['query']['bool']['must'][0]['term']['contact.city']['value'],
        );

        $negated = $parser->parseFilterString("NOT color:'red'");
        $this->assertSame("(NOT color:'red')", $negated);

        $negatedQuery = (new FilterParser($properties))->parse($negated)->toRaw();
        $this->assertSame(
            'red',
            $negatedQuery['bool']['must'][0]['bool']['must'][0]['bool']['must_not'][0]['term']['color']['value'],
        );
    }
}
