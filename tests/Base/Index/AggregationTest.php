<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Index;

use Sigmie\Base\APIs\Explain;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\APIs\Search;
use Sigmie\Base\Contracts\Aggregation;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Http\Responses\Search as SearchResponse;
use Sigmie\Base\Mappings\Blueprint;
use Sigmie\Base\Search\Aggs as SearchAggregation;
use Sigmie\Base\Search\Clauses\Boolean;
use Sigmie\Base\Search\Compound\Boolean as CompoundBoolean;
use Sigmie\Base\Search\Queries\Compound\Boolean as QueriesCompoundBoolean;
use Sigmie\Base\Search\QueryBuilder;
use Sigmie\Testing\TestCase;

class AggregationTest extends TestCase
{
    use Index, Search, Explain;

    /**
     * @test
     */
    public function min_aggregation()
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)->withoutMappings()->create();

        $collection = $this->sigmie->collect($name);

        $docs = [
            new Document([
                'count' => 5,
            ]),
            new Document([
                'count' => 15,
            ]),
            new Document([
                'count' => 233,
            ]),
        ];

        $collection->merge($docs);

        $res = $this->sigmie->search($name)
            ->matchAll()
            ->aggregate(function (SearchAggregation $aggregation) {

                $aggregation->min('minCount', 'count')
                    ->meta(['customer' => 'bar']);
            })
            ->get();

        $this->assertEquals(5, (int) $res->aggregation('minCount.value'));
        $this->assertEquals(['customer' => 'bar'], $res->aggregation('minCount.meta'));
    }

    /**
     * @test
     */
    public function avg_aggregation()
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)->withoutMappings()->create();

        $collection = $this->sigmie->collect($name);

        $docs = [
            new Document([
                'count' => 2,
            ]),
            new Document([
                'count' => 4,
            ]),
            new Document([
                'name' => 'john'
            ])
        ];

        $collection->merge($docs);

        $res = $this->sigmie->search($name)
            ->matchAll()
            ->aggregate(function (SearchAggregation $aggregation) {
                $aggregation->avg('averageCount', 'count');
            })
            ->get();

        $this->assertEquals(3, (int) $res->aggregation('averageCount.value'));

        $res = $this->sigmie->search($name)
            ->matchAll()
            ->aggregate(function (SearchAggregation $aggregation) {
                $aggregation->avg('averageCount', 'count')->missing(6);
            })
            ->get();



        $this->assertEquals(4, (int) $res->aggregation('averageCount.value'));
    }

    /**
     * @test
     */
    public function max_aggregation()
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)->withoutMappings()->create();

        $collection = $this->sigmie->collect($name);

        $docs = [
            new Document([
                'count' => 5,
            ]),
            new Document([
                'count' => 15,
            ]),
            new Document([
                'count' => 233,
            ]),
        ];

        $collection->merge($docs);

        $res = $this->sigmie->search($name)
            ->matchAll()
            ->aggregate(function (SearchAggregation $aggregation) {
                $aggregation->max('maxCount', 'count');
            })
            ->get();

        $this->assertEquals(233, (int) $res->aggregation('maxCount.value'));
    }
}
