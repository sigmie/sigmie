<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Carbon\Exceptions\ParseErrorException;
use Sigmie\Base\APIs\Explain;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\APIs\Search;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Testing\TestCase;

class FacetsTest extends TestCase
{
    use Explain;
    use Index;
    use Search;

    /**
     * @test
     */
    public function deeper_nested_price_facets()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->nested('shirt', function (NewProperties $blueprint) {
            $blueprint->nested('red', function (NewProperties $blueprint) {
                $blueprint->price();
            });
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['shirt' => ['red' => ['price' => 500]]]),
            new Document(['shirt' => ['red' => ['price' => 400]]]),
            new Document(['shirt' => ['red' => ['price' => 400]]]),
        ]);

        $searchResponse = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('')
            ->facets('shirt.red.price:100')
            ->get();

        /** @var Properties $props */
        $props = $blueprint();

        $field = $props->getNestedField('shirt.red.price');

        $facets = $field->facets($searchResponse->facetAggregations());

        $this->assertArrayHasKey('min', $facets);
        $this->assertEquals(400, $facets['min']);

        $this->assertArrayHasKey('max', $facets);
        $this->assertEquals(500, $facets['max']);

        $expectedHistogram = [
            400 => 2,
            500 => 1,
        ];

        $this->assertEquals($expectedHistogram, $facets['histogram']);
    }

    /**
     * @test
     */
    public function nested_price_facets()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->nested('shirt', function (NewProperties $blueprint) {
            $blueprint->price();
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['shirt' => ['price' => 500]]),
            new Document(['shirt' => ['price' => 400]]),
            new Document(['shirt' => ['price' => 400]]),
        ]);

        $searchResponse = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('')
            ->facets('shirt.price:100')
            ->get();

        /** @var Properties $props */
        $props = $blueprint();

        $field = $props->getNestedField('shirt.price');

        $facets = $field->facets($searchResponse->facetAggregations());

        $this->assertArrayHasKey('min', $facets);
        $this->assertEquals(400, $facets['min']);

        $this->assertArrayHasKey('max', $facets);
        $this->assertEquals(500, $facets['max']);

        $expectedHistogram = [
            400 => 2,
            500 => 1,
        ];

        $this->assertEquals($expectedHistogram, $facets['histogram']);
    }

    /**
     * @test
     */
    public function price_facets()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->price();

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['price' => 500]),
            new Document(['price' => 400]),
            new Document(['price' => 400]),
            new Document(['price' => 200]),
            new Document(['price' => 200]),
            new Document(['price' => 100]),
            new Document(['price' => 100]),
            new Document(['price' => 50]),
        ]);

        $searchResponse = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('')
            ->facets('price:100')
            ->get();

        /** @var Properties $props */
        $props = $blueprint();

        $facets = $props['price']->facets($searchResponse->facetAggregations());

        $this->assertArrayHasKey('min', $facets);
        $this->assertEquals(50, $facets['min']);

        $this->assertArrayHasKey('max', $facets);
        $this->assertEquals(500, $facets['max']);

        $expectedHistogram = [
            0 => 1,
            100 => 2,
            200 => 2,
            300 => 0,
            400 => 2,
            500 => 1,
        ];

        $this->assertEquals($expectedHistogram, $facets['histogram']);
    }

    /**
     * @test
     */
    public function nested_keywords_facets()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->nested('foo', function (NewProperties $blueprint) {
            $blueprint->keyword('keyword');
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['foo' => ['keyword' => 'sport']]),
            new Document(['foo' => ['keyword' => 'action']]),
        ]);

        $searchResponse = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('')
            ->facets('foo.keyword')
            ->get();

        /** @var Properties $props */
        $props = $blueprint();

        $facets = $props->getNestedField('foo.keyword')->facets($searchResponse->facetAggregations());

        $expectedHistogram = [
            'action' => 1,
            'sport' => 1,
        ];

        $this->assertEquals($expectedHistogram, $facets);
    }

    /**
     * @test
     */
    public function deeper_nested_keywords_facets()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->nested('foo', function (NewProperties $blueprint) {
            $blueprint->nested('bar', function (NewProperties $blueprint) {
                $blueprint->keyword('keyword');
            });
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['foo' => ['bar' => ['keyword' => 'sport']]]),
            new Document(['foo' => ['bar' => ['keyword' => 'action']]]),
        ]);

        $searchResponse = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('')
            ->facets('foo.bar.keyword')
            ->get();

        /** @var Properties $props */
        $props = $blueprint();

        $facets = $props->getNestedField('foo.bar.keyword')->facets($searchResponse->facetAggregations());

        $expectedHistogram = [
            'action' => 1,
            'sport' => 1,
        ];

        $this->assertEquals($expectedHistogram, $facets);
    }

    /**
     * @test
     */
    public function keywords_facets()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('keyword');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['keyword' => 'sport']),
            new Document(['keyword' => 'action']),
        ]);

        $searchResponse = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('')
            ->facets('keyword')
            ->get();

        /** @var Properties $props */
        $props = $blueprint();

        $facets = $props['keyword']->facets($searchResponse->facetAggregations());

        $expectedHistogram = [
            'action' => 1,
            'sport' => 1,
        ];

        $this->assertEquals($expectedHistogram, $facets);
    }

    /**
     * @test
     */
    public function text_bool_number_facets()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->keyword('keyword');
        $blueprint->text('text')->keyword();
        $blueprint->number('count');
        $blueprint->bool('active');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['keyword' => 'sport', 'text' => 'Some text about sport', 'count' => 1, 'active' => true]),
            new Document(['keyword' => 'action', 'text' => 'Some text about action', 'count' => 2, 'active' => false]),
        ]);

        $searchResponse = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('')
            ->facets('keyword count text')
            ->get();

        /** @var Properties $props */
        $props = $blueprint();

        $facets = $props['keyword']->facets($searchResponse->facetAggregations());

        $expectedHistogram = [
            'action' => 1,
            'sport' => 1,
        ];

        $this->assertEquals($expectedHistogram, $facets);

        $expectedCountFacets = [
            'count' => 2,
            'min' => 1.0,
            'max' => 2.0,
            'avg' => 1.5,
            'sum' => 3.0,
        ];

        $countFacets = $props['count']->facets($searchResponse->facetAggregations());

        $this->assertEquals($expectedCountFacets, $countFacets);

        $expectedTextFacets = [
            'Some text about sport' => 1,
            'Some text about action' => 1,
        ];

        $textFacets = $props['text']->facets($searchResponse->facetAggregations());

        $this->assertEquals($expectedTextFacets, $textFacets);
    }

    /**
     * @test
     */
    public function category_facets()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->category();

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['category' => 'sport']),
            new Document(['category' => 'action']),
        ]);

        $searchResponse = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('')
            ->facets('category')
            ->get();

        /** @var Properties $props */
        $props = $blueprint();

        $facets = $props['category']->facets($searchResponse->facetAggregations());

        $expectedHistogram = [
            'action' => 1,
            'sport' => 1,
        ];

        $this->assertEquals($expectedHistogram, $facets);
    }

    /**
     * @test
     */
    public function nested_category_facets()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->nested('category', function (NewProperties $blueprint) {
            $blueprint->nested('sport', function (NewProperties $blueprint) {
                $blueprint->keyword('type');
            });
        });

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['category' => ['sport' => ['type' => 'sport']]]),
            new Document(['category' => ['sport' => ['type' => 'action']]]),
        ]);

        $searchResponse = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('')
            ->facets('category.sport.type')
            ->get();

        /** @var Properties $props */
        $props = $blueprint();

        $facets = $props->getNestedField('category.sport.type')->facets($searchResponse->facetAggregations());

        $expectedHistogram = [
            'action' => 1,
            'sport' => 1,
        ];

        $this->assertEquals($expectedHistogram, $facets);
    }

    /**
     * @test
     */
    public function facet_exclusion_logic()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->category('color')->facetDisjunctive();
        $blueprint->category('size')->facetDisjunctive();
        $blueprint->category('type')->facetDisjunctive();
        $blueprint->number('stock');

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'color' => 'red',
                'size' => 'xl',
                'type' => 'shirt',
                'stock' => 10,
            ]),
            new Document([
                'color' => 'red',
                'size' => 'lg',
                'type' => 'pants',
                'stock' => 20,
            ]),
            new Document([
                'color' => 'green',
                'size' => 'md',
                'type' => 'jacket',
                'stock' => 30,
            ]),
            new Document([
                'color' => 'green',
                'size' => 'xs',
                'type' => 'jacket',
                'stock' => 0,
            ]),
        ]);

        $searchResponse = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('')
            ->filters("stock>'0'")
            ->facets('color size', "color:'green'")
            ->get();

        $facets = (array) $searchResponse->json('facets');

        $this->assertArrayHasKey('size', $facets);
        $this->assertArrayHasKey('color', $facets);

        $colors = (array) $facets['color'];
        $sizes = (array) $facets['size'];

        // Should include both green (self excluded) and red
        $this->assertArrayHasKey('green', $colors);
        $this->assertArrayHasKey('red', $colors);

        // Should include only md is the only size in green color that has stock
        $this->assertArrayHasKey('md', $sizes);
    }

    /**
     * @test
     */
    public function facet_disjunctive()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->category('color')->facetDisjunctive();
        $blueprint->category('size')->facetDisjunctive();
        $blueprint->price();

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'color' => ['red', 'blue'],
                'size' => 'lg',
                'price' => 100,
            ]),
            new Document([
                'color' => 'red',
                'size' => 'lg',
                'price' => 150,
            ]),
            new Document([
                'color' => 'blue',
                'size' => 'lg',
                'price' => 200,
            ]),
        ]);

        $searchResponse = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('')
            ->facets('color size', "color:'red' color:'blue' size:'lg' price:100..200")
            ->get();

        $res = $searchResponse->json();

        $facets = (array) $res['facets'];

        $color = (array) $facets['color'];

        $this->assertArrayHasKey('red', $color);
        $this->assertArrayHasKey('blue', $color);

        $this->assertCount(3, (array) $res['hits']);
    }

    /**
     * @test
     */
    public function facet_conjunctive()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->category('color')->facetConjunctive();
        $blueprint->category('size')->facetConjunctive();
        $blueprint->price();

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'color' => ['red', 'blue'],
                'size' => 'xl',
                'price' => 100,
            ]),
            new Document([
                'color' => 'red',
                'size' => 'lg',
                'price' => 150,
            ]),
            new Document([
                'color' => 'blue',
                'size' => 'lg',
                'price' => 200,
            ]),
        ]);

        $searchResponse = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('')
            ->facets('color size', filters: "color:'red' color:'blue' price:100..200")
            ->get();

        $res = $searchResponse->json();

        $facets = (array) $res['facets'];

        $color = (array) $facets['color'];

        $this->assertArrayHasKey('red', $color);
        $this->assertArrayHasKey('blue', $color);

        $this->assertCount(1, (array) $res['hits']);
    }

    /**
     * @test
     */
    public function facet_parenthetic_expressions()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->price();

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'price' => 100,
            ]),
            new Document([
                'price' => 200,
            ]),
            new Document([
                'price' => 300,
            ]),
            new Document([
                'price' => 400,
            ]),
        ]);

        $searchResponse = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('')
            ->facets('price', "(price:100..200)")
            ->get();

        $this->assertNotEmpty($searchResponse->json('errors'));
    }
}
