<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Base\APIs\Explain;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\APIs\Search;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Query\Aggregations\Enums\CalendarInterval;
use Sigmie\Query\Aggs as SearchAggregation;
use Sigmie\Testing\TestCase;

class FacetsTest extends TestCase
{
    use Index;
    use Search;
    use Explain;

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

        $facets = $props['price']->facets($searchResponse);

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

        $facets = $props['keyword']->facets($searchResponse);

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

        $facets = $props['keyword']->facets($searchResponse);

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

        $countFacets = $props['count']->facets($searchResponse);

        $this->assertEquals($expectedCountFacets, $countFacets);

        $expectedTextFacets = [
            'Some text about sport' => 1,
            'Some text about action' => 1,
        ];

        $textFacets = $props['text']->facets($searchResponse);

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
            new Document(['category' => 'sport',]),
            new Document(['category' => 'action',]),
        ]);

        $searchResponse = $this->sigmie->newSearch($indexName)
            ->properties($blueprint())
            ->queryString('')
            ->facets('category')
            ->get();

        /** @var Properties $props */
        $props = $blueprint();

        $facets = $props['category']->facets($searchResponse);

        $expectedHistogram = [
            'action' => 1,
            'sport' => 1,
        ];

        $this->assertEquals($expectedHistogram, $facets);
    }
}
