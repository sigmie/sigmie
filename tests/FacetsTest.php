<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Base\APIs\Explain;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\APIs\Search;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Testing\TestCase;

class FacetsTest extends TestCase
{
    use Explain;
    use Index;
    use Search;

    /**
     * @test
     */
    public function deeper_nested_price_facets(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->nested('shirt', function (NewProperties $blueprint): void {
            $blueprint->nested('red', function (NewProperties $blueprint): void {
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

        $facets = $searchResponse->facet('shirt.red.price');

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
    public function nested_price_facets(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->nested('shirt', function (NewProperties $blueprint): void {
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

        $facets = $searchResponse->facet('shirt.price');

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
    public function price_facets(): void
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

        $facets = $searchResponse->facet('price');

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
    public function nested_keywords_facets(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->nested('foo', function (NewProperties $blueprint): void {
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

        $facets = $searchResponse->facet('foo.keyword');

        $expectedHistogram = [
            'action' => 1,
            'sport' => 1,
        ];

        $this->assertEquals($expectedHistogram, $facets);
    }

    /**
     * @test
     */
    public function deeper_nested_keywords_facets(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->nested('foo', function (NewProperties $blueprint): void {
            $blueprint->nested('bar', function (NewProperties $blueprint): void {
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

        $facets = $searchResponse->facet('foo.bar.keyword');

        $expectedHistogram = [
            'action' => 1,
            'sport' => 1,
        ];

        $this->assertEquals($expectedHistogram, $facets);
    }

    /**
     * @test
     */
    public function keywords_facets(): void
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

        $facets = $searchResponse->facet('keyword');

        $expectedHistogram = [
            'action' => 1,
            'sport' => 1,
        ];

        $this->assertEquals($expectedHistogram, $facets);
    }

    /**
     * @test
     *
     * @dataProvider everyFacetableTypeOnEveryDepth
     */
    public function every_facetable_type_on_root_and_nested_fields(string $facet, string $field, array $expected): void
    {
        $indexName = uniqid();

        $blueprint = $this->everyFacetableTypeBlueprint();

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)->merge($this->everyFacetableTypeDocuments());

        $searchResponse = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('')
            ->facets($facet)
            ->get();

        $this->assertEquals($expected, $searchResponse->facet($field));
        $this->assertEquals($expected, json_decode(json_encode($searchResponse->json('facets')), true)[$field] ?? null);
    }

    public static function everyFacetableTypeOnEveryDepth(): array
    {
        $expected = [
            'keyword' => ['action' => 1, 'sport' => 1],
            'tag' => ['Sport' => 1, 'sport' => 1],
            'count' => ['count' => 2, 'min' => 1.0, 'max' => 3.0, 'avg' => 2.0, 'sum' => 4.0],
            'price' => ['min' => 100.0, 'max' => 300.0, 'histogram' => [100 => 1, 200 => 0, 300 => 1]],
            'text' => ['Other text' => 1, 'Some text' => 1],
        ];

        $cases = [];

        foreach (['root' => '', 'nested' => 'shirt.', 'two-level nested' => 'shirt.red.'] as $depth => $prefix) {
            foreach ($expected as $name => $facets) {
                $field = $prefix.$name;
                $param = $name === 'price' ? ':100' : '';

                $cases["{$depth} {$name}"] = [$field.$param, $field, $facets];
            }
        }

        return $cases;
    }

    /**
     * @test
     */
    public function facet_filter_on_nested_field(): void
    {
        $indexName = uniqid();

        $blueprint = $this->everyFacetableTypeBlueprint();

        $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $this->sigmie->collect($indexName, refresh: true)->merge($this->everyFacetableTypeDocuments());

        $searchResponse = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('')
            ->facets('shirt.count shirt.keyword', "shirt.keyword:'sport'")
            ->get();

        $this->assertEquals(
            ['count' => 1, 'min' => 1.0, 'max' => 1.0, 'avg' => 1.0, 'sum' => 1.0],
            $searchResponse->facet('shirt.count'),
        );
        $this->assertEquals(['action' => 1, 'sport' => 1], $searchResponse->facet('shirt.keyword'));
    }

    private function everyFacetableTypeBlueprint(): NewProperties
    {
        $fields = function (NewProperties $blueprint): void {
            $blueprint->keyword('keyword');
            $blueprint->caseSensitiveKeyword('tag');
            $blueprint->number('count');
            $blueprint->price();
            $blueprint->text('text')->keyword();
        };

        $blueprint = new NewProperties;
        $fields($blueprint);
        $blueprint->nested('shirt', function (NewProperties $blueprint) use ($fields): void {
            $fields($blueprint);
            $blueprint->nested('red', $fields);
        });

        return $blueprint;
    }

    /**
     * @return array<int, Document>
     */
    private function everyFacetableTypeDocuments(): array
    {
        $values = [
            ['keyword' => 'sport', 'tag' => 'Sport', 'count' => 1, 'price' => 100, 'text' => 'Some text'],
            ['keyword' => 'action', 'tag' => 'sport', 'count' => 3, 'price' => 300, 'text' => 'Other text'],
        ];

        return array_map(
            fn (array $value): Document => new Document([...$value, 'shirt' => [...$value, 'red' => $value]]),
            $values,
        );
    }

    /**
     * @test
     */
    public function text_bool_number_facets(): void
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

        $facets = $searchResponse->facet('keyword');

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

        $countFacets = $searchResponse->facet('count');

        $this->assertEquals($expectedCountFacets, $countFacets);

        $expectedTextFacets = [
            'Some text about sport' => 1,
            'Some text about action' => 1,
        ];

        $textFacets = $searchResponse->facet('text');

        $this->assertEquals($expectedTextFacets, $textFacets);
    }

    /**
     * @test
     */
    public function category_facets(): void
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

        $facets = $searchResponse->facet('category');

        $expectedHistogram = [
            'action' => 1,
            'sport' => 1,
        ];

        $this->assertEquals($expectedHistogram, $facets);
    }

    /**
     * @test
     */
    public function nested_category_facets(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->nested('category', function (NewProperties $blueprint): void {
            $blueprint->nested('sport', function (NewProperties $blueprint): void {
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

        $facets = $searchResponse->facet('category.sport.type');

        $expectedHistogram = [
            'action' => 1,
            'sport' => 1,
        ];

        $this->assertEquals($expectedHistogram, $facets);
    }

    /**
     * @test
     */
    public function facet_exclusion_logic(): void
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
    public function facet_disjunctive(): void
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
    public function facet_conjunctive(): void
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
    public function facet_counts_reflect_text_query(): void
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('name');
        $blueprint->category('color')->facetDisjunctive();

        $index = $this->sigmie->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document(['name' => 'red shoes', 'color' => 'red']),
            new Document(['name' => 'blue shoes', 'color' => 'blue']),
            new Document(['name' => 'red hat', 'color' => 'red']),
        ]);

        $searchResponse = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('shoes')
            ->facets('color')
            ->get();

        $res = $searchResponse->json();

        $this->assertCount(2, (array) $res['hits']);

        $facets = (array) $res['facets'];
        $colors = (array) $facets['color'];

        $this->assertEquals(1, $colors['red']);
        $this->assertEquals(1, $colors['blue']);
    }

    /**
     * @test
     */
    public function facet_parenthetic_expressions(): void
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
            ->facets('price', '(price:100..200)')
            ->get();

        $this->assertNotEmpty($searchResponse->json('errors'));
    }
}
