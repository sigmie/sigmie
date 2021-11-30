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
    public function percentile_ranks_aggregation()
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)->withoutMappings()->create();

        $collection = $this->sigmie->collect($name);

        $docs = [
            new Document([
                'type' => 1
            ]),
            new Document([
                'type' => 2
            ]),
            new Document([
                'type' => 3
            ]),
            new Document([
                'type' => 3
            ]),
            new Document([
                'type' => 3
            ]),
        ];

        $collection->merge($docs);

        $res = $this->sigmie->search($name)
            ->matchAll()
            ->aggregate(function (SearchAggregation $aggregation) {
                $aggregation->percentileRanks('percentile_rank', 'type', [3, 2]);
            })
            ->get();

        $value = $res->aggregation('percentile_rank.values');

        $this->assertArrayHasKey('3.0', $value);
        $this->assertArrayHasKey('2.0', $value);
        $this->assertArrayNotHasKey('5.0', $value);
    }

    /**
     * @test
     */
    public function percentiles_aggregation()
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)->withoutMappings()->create();

        $collection = $this->sigmie->collect($name);

        $docs = [
            new Document([
                'type' => 1
            ]),
            new Document([
                'type' => 2
            ]),
            new Document([
                'type' => 3
            ]),
            new Document([
                'type' => 3
            ]),
            new Document([
                'type' => 3
            ]),
        ];

        $collection->merge($docs);

        $res = $this->sigmie->search($name)
            ->matchAll()
            ->aggregate(function (SearchAggregation $aggregation) {
                $aggregation->percentiles('percentile', 'type', [1, 2]);
            })
            ->get();

        $value = $res->aggregation('percentile.values');

        $this->assertArrayHasKey('1.0', $value);
        $this->assertArrayHasKey('2.0', $value);
        $this->assertArrayNotHasKey('5.0', $value);
    }

    /**
     * @test
     */
    public function cardinality_aggregation()
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)->withoutMappings()->create();

        $collection = $this->sigmie->collect($name);

        $docs = [
            new Document([
                'type' => 1
            ]),
            new Document([
                'type' => 2
            ]),
            new Document([
                'type' => 3
            ]),
            new Document([
                'type' => 3
            ]),
        ];

        $collection->merge($docs);

        $res = $this->sigmie->search($name)
            ->matchAll()
            ->aggregate(function (SearchAggregation $aggregation) {
                $aggregation->cardinality('type_count', 'type');
            })
            ->get();

        $value = $res->aggregation('type_count.value');

        $this->assertEquals(3, (int)$value);
    }

    /**
     * @test
     */
    public function value_count_aggregation()
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)->withoutMappings()->create();

        $collection = $this->sigmie->collect($name);

        $docs = [
            new Document([
                'type' => 1
            ]),
            new Document([
                'type' => 2
            ]),
            new Document([
                'type' => 3
            ]),
            new Document([
                'type' => 3
            ]),
        ];

        $collection->merge($docs);

        $res = $this->sigmie->search($name)
            ->matchAll()
            ->aggregate(function (SearchAggregation $aggregation) {
                $aggregation->valueCount('type_count', 'type');
            })
            ->get();

        $value = $res->aggregation('type_count.value');

        $this->assertEquals(4, (int)$value);
    }

    /**
     * @test
     */
    public function sum_aggregation()
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)->withoutMappings()->create();

        $collection = $this->sigmie->collect($name);

        $docs = [
            new Document([
                'count' => 20,
            ]),
            new Document([
                'count' => 20,
            ]),
            new Document([
                'count' => 20,
            ]),
        ];

        $collection->merge($docs);

        $res = $this->sigmie->search($name)
            ->matchAll()
            ->aggregate(function (SearchAggregation $aggregation) {
                $aggregation->sum('count_sum', 'count');
            })
            ->get();

        $value = $res->aggregation('count_sum.value');

        $this->assertEquals(60, (int)$value);
    }

    /**
     * @test
     */
    public function stats_aggregation()
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
                $aggregation->stats('stats', 'count');
            })
            ->get();

        $stats = $res->aggregation('stats');

        $this->assertArrayHasKey('count', $stats);
        $this->assertArrayHasKey('min', $stats);
        $this->assertArrayHasKey('max', $stats);
        $this->assertArrayHasKey('avg', $stats);
        $this->assertArrayHasKey('sum', $stats);
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
