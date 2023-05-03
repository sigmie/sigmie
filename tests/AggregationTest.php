<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Base\APIs\Explain;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\APIs\Search;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Query\Aggregations\Enums\CalendarInterval;
use Sigmie\Query\Aggs as SearchAggregation;
use Sigmie\Testing\TestCase;

class AggregationTest extends TestCase
{
    use Index;
    use Search;
    use Explain;

    /**
     * @test
     */
    public function significant_text_aggregation()
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)->mapping(function (NewProperties $blueprint) {
            $blueprint->text('title')->unstructuredText();
        })->create();

        $collection = $this->sigmie->collect($name);

        $docs = [
            new Document([
                'title' => 'Chocolate is very tasty.',
            ]),
            new Document([
                'title' => 'Dory is looking for Nemo.',
            ]),
            new Document([
                'title' => 'Buz is like Woody, very handsome.',
            ]),
            new Document([
                'title' => 'Chocolate makes me fat. ',
            ]),
        ];

        $collection->merge($docs);

        $res = $this->sigmie->newQuery($name)
            ->matchAll()
            ->aggregate(function (SearchAggregation $aggregation) {
                $aggregation->significantText('significant', 'title');
            })
            ->get();

        $value = $res->aggregation('significant.buckets');

        $this->assertCount(0, $value);
    }

    /**
     * @test
     */
    public function date_histogram_aggregation()
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)->create();

        $collection = $this->sigmie->collect($name, refresh: true);

        $docs = [
            new Document([
                'date' => '2020-01-01',
            ]),
            new Document([
                'date' => '2019-01-01',
            ]),
            new Document([
                'date' => '2018-01-01',
            ]),
            new Document([
                'date' => '2018-01-01',
            ]),
            new Document([
                'name' => 'nico',
            ]),
            new Document([
                'date' => '2016-01-01',
            ]),
            new Document([
                'date' => '1999-01-01',
            ]),
        ];

        $collection->merge($docs);

        $res = $this->sigmie->newQuery($name)
            ->matchAll()
            ->aggregate(function (SearchAggregation $aggregation) {
                $aggregation->dateHistogram('histogram', 'date', CalendarInterval::Year)

                    ->aggregate(function (SearchAggregation $aggregation) {
                        $aggregation->dateHistogram('histogram_nested', 'date', CalendarInterval::Day)
                            ->missing('2021-01-01');
                    })
                    ->missing('2021-01-01');
            })
            ->get();

        $value = $res->aggregation('histogram');

        $this->assertArrayHasKey('buckets', $value);
        $this->assertArrayHasKey('histogram_nested', $res->aggregation('histogram.buckets.0'));
    }

    /**
     * @test
     */
    public function ranges_aggregation()
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)->mapping(function (NewProperties $blueprint) {
            $blueprint->keyword('type');
        })->create();

        $collection = $this->sigmie->collect($name);

        $docs = [
            new Document([
                'price' => 200,
            ]),
            new Document([
                'price' => 100,
            ]),
            new Document([
                'price' => 150,
            ]),
            new Document([
                'price' => 300,
            ]),
        ];

        $collection->merge($docs);

        $res = $this->sigmie->newQuery($name)
            ->matchAll()
            ->aggregate(function (SearchAggregation $aggregation) {
                $aggregation->range('price_ranges', 'price', [
                    ['to' => 100],
                    ['from' => 200, 'to' => 400],
                    ['from' => 300],
                ]);
            })
            ->get();

        $value = $res->aggregation('price_ranges.buckets');

        $this->assertCount(3, $value);
    }

    /**
     * @test
     */
    public function terms_aggregation()
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)->mapping(function (NewProperties $blueprint) {
            $blueprint->keyword('type');
        })->create();

        $collection = $this->sigmie->collect($name, refresh: true);

        $docs = [
            new Document([
                'type' => 'woman',
            ]),
            new Document([
                'type' => 'man',
            ]),
            new Document([
                'type' => 'man',
            ]),
            new Document([
                'type' => 'child',
            ]),
            new Document([
                'type' => 'child',
            ]),
            new Document([
                'name' => 'Nico',
            ]),
        ];

        $collection->merge($docs);

        $res = $this->sigmie->newQuery($name)
            ->matchAll()
            ->aggregate(function (SearchAggregation $aggregation) {
                $aggregation->terms('genders', 'type')->missing('N/A');
            })
            ->get();

        $value = $res->aggregation('genders.buckets');

        $this->assertCount(4, $value);
    }

    /**
     * @test
     */
    public function min_aggregation()
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)->create();

        $collection = $this->sigmie->collect($name, refresh: true);

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

        $res = $this->sigmie->newQuery($name)
            ->matchAll()
            ->aggregate(function (SearchAggregation $aggregation) {
                $aggregation->min('minCount', 'count');
            })
            ->get();

        $this->assertEquals(5, (int) $res->aggregation('minCount.value'));
    }

    /**
     * @test
     */
    public function avg_aggregation()
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)->create();

        $collection = $this->sigmie->collect($name, refresh: true);

        $docs = [
            new Document([
                'count' => 2,
            ]),
            new Document([
                'count' => 4,
            ]),
            new Document([
                'name' => 'john',
            ]),
        ];

        $collection->merge($docs);

        $res = $this->sigmie->newQuery($name)
            ->matchAll()
            ->aggregate(function (SearchAggregation $aggregation) {
                $aggregation->avg('averageCount', 'count');
            })
            ->get();

        $this->assertEquals(3, (int) $res->aggregation('averageCount.value'));

        $res = $this->sigmie->newQuery($name)
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

        $this->sigmie->newIndex($name)->create();

        $collection = $this->sigmie->collect($name, refresh: true);

        $docs = [
            new Document([
                'type' => 1,
            ]),
            new Document([
                'type' => 2,
            ]),
            new Document([
                'type' => 3,
            ]),
            new Document([
                'type' => 3,
            ]),
            new Document([
                'type' => 3,
            ]),
        ];

        $collection->merge($docs);

        $res = $this->sigmie->newQuery($name)
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

        $this->sigmie->newIndex($name)->create();

        $collection = $this->sigmie->collect($name, refresh: true);

        $docs = [
            new Document([
                'type' => 1,
            ]),
            new Document([
                'type' => 2,
            ]),
            new Document([
                'type' => 3,
            ]),
            new Document([
                'type' => 3,
            ]),
            new Document([
                'type' => 3,
            ]),
        ];

        $collection->merge($docs);

        $res = $this->sigmie->newQuery($name)
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

        $this->sigmie->newIndex($name)->create();

        $collection = $this->sigmie->collect($name, refresh: true);

        $docs = [
            new Document([
                'type' => 1,
            ]),
            new Document([
                'type' => 2,
            ]),
            new Document([
                'type' => 3,
            ]),
            new Document([
                'type' => 3,
            ]),
        ];

        $collection->merge($docs);

        $res = $this->sigmie->newQuery($name)
            ->matchAll()
            ->aggregate(function (SearchAggregation $aggregation) {
                $aggregation->cardinality('type_count', 'type');
            })
            ->get();

        $value = $res->aggregation('type_count.value');

        $this->assertEquals(3, (int) $value);
    }

    /**
     * @test
     */
    public function value_count_aggregation()
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)->create();

        $collection = $this->sigmie->collect($name, refresh: true);

        $docs = [
            new Document([
                'type' => 1,
            ]),
            new Document([
                'type' => 2,
            ]),
            new Document([
                'type' => 3,
            ]),
            new Document([
                'type' => 3,
            ]),
        ];

        $collection->merge($docs);

        $res = $this->sigmie->newQuery($name)
            ->matchAll()
            ->aggregate(function (SearchAggregation $aggregation) {
                $aggregation->valueCount('type_count', 'type');
            })
            ->get();

        $value = $res->aggregation('type_count.value');

        $this->assertEquals(4, (int) $value);
    }

    /**
     * @test
     */
    public function sum_aggregation()
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)->create();

        $collection = $this->sigmie->collect($name, refresh: true);

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

        $res = $this->sigmie->newQuery($name)
            ->matchAll()
            ->aggregate(function (SearchAggregation $aggregation) {
                $aggregation->sum('count_sum', 'count');
            })
            ->get();

        $value = $res->aggregation('count_sum.value');

        $this->assertEquals(60, (int) $value);
    }

    /**
     * @test
     */
    public function stats_aggregation()
    {
        $name = uniqid();

        $this->sigmie->newIndex($name)->create();

        $collection = $this->sigmie->collect($name, refresh: true);

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

        $res = $this->sigmie->newQuery($name)
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

        $this->sigmie->newIndex($name)->create();

        $collection = $this->sigmie->collect($name, refresh: true);

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

        $res = $this->sigmie->newQuery($name)
            ->matchAll()
            ->aggregate(function (SearchAggregation $aggregation) {
                $aggregation->max('maxCount', 'count');
            })
            ->get();

        $this->assertEquals(233, (int) $res->aggregation('maxCount.value'));
    }
}
