<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use InvalidArgumentException;
use Sigmie\Analytics\QueryRecipe;
use Sigmie\Mappings\NewProperties;
use Sigmie\Sigmie;
use Sigmie\SigmieIndex;
use Sigmie\Testing\TestCase;

class QueryRecipeTest extends TestCase
{
    /** @test */
    public function it_binds_slots_and_keeps_the_dataset_in_canonical_identity(): void
    {
        $orders = QueryRecipe::fromArray($this->definition('orders', [
            'widget' => 'breakdown',
            'date_field' => 'occurred_at',
            'metric' => 'sum',
            'field' => 'amount',
            'group_by' => 'category',
            'limit' => 5,
        ], [[
            'name' => 'limit',
            'target' => 'limit',
            'type' => 'integer',
            'minimum' => 1,
            'maximum' => 100,
            'required' => false,
        ]]));
        $products = QueryRecipe::fromArray($this->definition('products', [
            'widget' => 'breakdown',
            'date_field' => 'occurred_at',
            'metric' => 'sum',
            'field' => 'amount',
            'group_by' => 'category',
            'limit' => 5,
        ], [[
            'name' => 'limit',
            'target' => 'limit',
            'type' => 'integer',
            'required' => false,
        ]]));

        $this->assertNotSame($orders->hash(), $products->hash());
        $this->assertSame(20, QueryRecipe::fromArray($orders->toArray())->bind(['limit' => 20])->toArray()['limit']);
    }

    /** @test */
    public function it_reloads_a_recipe_with_a_slotted_required_widget_argument(): void
    {
        $recipe = QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'retention',
            'date_field' => 'occurred_at',
            'cohort_field' => 'occurred_at',
            'id_field' => 'user_id',
            'interval' => 'week',
        ], [[
            'name' => 'cadence',
            'target' => 'interval',
            'type' => 'string',
            'required' => true,
        ]]));

        $bound = QueryRecipe::fromArray($recipe->toArray())->bind(['cadence' => 'month'])->toArray();

        $this->assertSame('month', $bound['interval']);
    }

    /** @test */
    public function it_validates_every_supported_widget_against_an_elasticsearch_mapping(): void
    {
        $index = $this->recipeIndex();

        foreach ($this->widgetTemplates() as $widget => $template) {
            $recipe = QueryRecipe::fromArray($this->definition('events', $template));
            $this->assertSame($recipe, $recipe->validateAgainst($index), sprintf('Widget %s did not validate.', $widget));
        }
    }

    /** @test */
    public function it_rejects_a_filter_slot_that_does_not_match_the_index_field_type(): void
    {
        $index = $this->recipeIndex();
        $recipe = QueryRecipe::fromArray([
            ...$this->definition('events', [
                'widget' => 'kpi',
                'date_field' => 'occurred_at',
                'metric' => 'count',
            ], [[
                'name' => 'minimum',
                'target' => null,
                'type' => 'string',
                'required' => true,
            ]]),
            'filter_templates' => [[
                'field' => 'amount',
                'operator' => 'gte',
                'slot' => 'minimum',
            ]],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $recipe->validateAgainst($index);
    }

    /** @test */
    public function it_accepts_numeric_and_boolean_grouping_fields_supported_by_terms_aggregations(): void
    {
        $index = $this->recipeIndex();

        foreach (['year', 'active'] as $groupBy) {
            $recipe = QueryRecipe::fromArray($this->definition('events', [
                'widget' => 'breakdown',
                'date_field' => 'occurred_at',
                'metric' => 'avg',
                'field' => 'amount',
                'group_by' => $groupBy,
            ]));

            $this->assertSame($recipe, $recipe->validateAgainst($index));
        }
    }

    /**
     * @param  array<string, mixed>  $template
     * @param  list<array<string, mixed>>  $slots
     * @return array<string, mixed>
     */
    private function definition(string $dataset, array $template, array $slots = []): array
    {
        return [
            'dataset' => $dataset,
            'template' => $template,
            'slots' => $slots,
            'filter_templates' => [],
        ];
    }

    private function recipeIndex(): SigmieIndex
    {
        $index = new class($this->sigmie) extends SigmieIndex
        {
            private string $indexName;

            public function __construct(Sigmie $sigmie)
            {
                parent::__construct($sigmie);
                $this->indexName = uniqid('query_recipes_', true);
            }

            public function name(): string
            {
                return $this->indexName;
            }

            public function properties(): NewProperties
            {
                $properties = new NewProperties;
                $properties->datetime('occurred_at');
                $properties->double('amount');
                $properties->keyword('category');
                $properties->keyword('subcategory');
                $properties->keyword('user_id');
                $properties->integer('year');
                $properties->bool('active');
                $properties->geoPoint('location');

                return $properties;
            }
        };
        $index->create();

        return $index;
    }

    /** @return array<string, array<string, mixed>> */
    private function widgetTemplates(): array
    {
        $base = ['date_field' => 'occurred_at'];

        return [
            'kpi' => [...$base, 'widget' => 'kpi', 'metric' => 'sum', 'field' => 'amount'],
            'kpi_delta' => [...$base, 'widget' => 'kpi_delta', 'metric' => 'sum', 'field' => 'amount'],
            'trend' => [...$base, 'widget' => 'trend', 'metric' => 'sum', 'field' => 'amount'],
            'cumulative' => [...$base, 'widget' => 'cumulative', 'metric' => 'sum', 'field' => 'amount', 'interval' => 'day'],
            'grouped_trend' => [...$base, 'widget' => 'grouped_trend', 'metric' => 'sum', 'field' => 'amount', 'group_by' => 'category', 'interval' => 'day'],
            'breakdown' => [...$base, 'widget' => 'breakdown', 'metric' => 'sum', 'field' => 'amount', 'group_by' => 'category'],
            'multi_breakdown' => [...$base, 'widget' => 'multi_breakdown', 'metric' => 'sum', 'field' => 'amount', 'group_by_fields' => 'category,subcategory'],
            'distribution' => [...$base, 'widget' => 'distribution', 'field' => 'amount'],
            'histogram_metric' => [...$base, 'widget' => 'histogram_metric', 'metric' => 'avg', 'field' => 'amount', 'bucket_field' => 'amount', 'bucket_size' => 10],
            'grouped_metrics' => [...$base, 'widget' => 'grouped_metrics', 'group_by' => 'category', 'metrics' => '[{"key":"avg_amount","label":"Average amount","metric":"avg","field":"amount"}]'],
            'percentiles' => [...$base, 'widget' => 'percentiles', 'field' => 'amount', 'percents' => '50,95'],
            'stats' => [...$base, 'widget' => 'stats', 'field' => 'amount'],
            'table' => [...$base, 'widget' => 'table', 'fields' => 'category,amount', 'sort' => 'amount:desc'],
            'funnel' => [...$base, 'widget' => 'funnel', 'steps' => '[{"label":"All","filter":"category:\'all\'"}]'],
            'heatmap' => [...$base, 'widget' => 'heatmap', 'metric' => 'sum', 'field' => 'amount', 'row_field' => 'category', 'col_field' => 'subcategory'],
            'retention' => [...$base, 'widget' => 'retention', 'cohort_field' => 'occurred_at', 'id_field' => 'user_id', 'interval' => 'week'],
            'geo' => [...$base, 'widget' => 'geo', 'field' => 'location', 'precision' => 5],
        ];
    }
}
