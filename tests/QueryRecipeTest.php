<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use InvalidArgumentException;
use Sigmie\Analytics\AnalyticsRequest;
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
    public function it_promotes_result_limits_to_a_reusable_slot(): void
    {
        $recipe = QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'trend',
            'date_field' => 'occurred_at',
            'metric' => 'avg',
            'field' => 'amount',
            'limit' => 25,
        ]));

        $definition = $recipe->toArray();

        $this->assertArrayNotHasKey('limit', $definition['template']);
        $this->assertSame('limit', $definition['slots'][0]['target']);
        $this->assertSame(25, $recipe->bind([])->toArray()['limit']);
        $this->assertSame(100, $recipe->bind(['limit' => 100])->toArray()['limit']);
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
    public function it_uses_a_declared_slot_default_when_the_template_omits_the_target(): void
    {
        $recipe = QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'trend',
            'date_field' => 'occurred_at',
            'metric' => 'avg',
            'field' => 'amount',
        ], [[
            'name' => 'cadence',
            'target' => 'interval',
            'type' => 'string',
            'default' => 'day',
            'required' => false,
        ]]));

        $this->assertSame('day', $recipe->bind([])->toArray()['interval']);
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

    /** @test */
    public function it_rejects_slot_types_that_do_not_match_their_analytics_target(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('type [date] is incompatible with target [range]');

        QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'trend',
            'date_field' => 'occurred_at',
            'metric' => 'avg',
            'field' => 'amount',
        ], [[
            'name' => 'period',
            'target' => 'range',
            'type' => 'date',
            'required' => false,
        ]]));
    }

    /** @test */
    public function it_rejects_an_unsupported_period_before_execution(): void
    {
        $recipe = QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'trend',
            'date_field' => 'occurred_at',
            'metric' => 'avg',
            'field' => 'amount',
        ], [[
            'name' => 'period',
            'target' => 'range',
            'type' => 'period',
            'required' => true,
        ]]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a supported period');

        $recipe->bind(['period' => 'since_launch']);
    }

    /** @test */
    public function it_enforces_bindings_and_builds_typed_filter_clauses(): void
    {
        $definition = [
            ...$this->definition('events', [
                'widget' => 'kpi',
                'date_field' => 'occurred_at',
                'metric' => 'count',
                'filters' => "active:'true'",
            ], [
                ['name' => 'category', 'target' => null, 'type' => 'string', 'required' => true],
                ['name' => 'minimum', 'target' => null, 'type' => 'number', 'required' => false],
            ]),
            'filter_templates' => [
                ['field' => 'category', 'operator' => 'equals', 'slot' => 'category'],
                ['field' => 'amount', 'operator' => 'gte', 'slot' => 'minimum'],
            ],
        ];
        $recipe = QueryRecipe::fromArray($definition);

        $this->assertInvalid(fn (): AnalyticsRequest => $recipe->bind([]), 'binding [category] is required');
        $this->assertInvalid(fn (): AnalyticsRequest => $recipe->bind(['unknown' => 1]), 'Unknown query recipe bindings');

        $filters = $recipe->bind([
            'category' => "O'Reilly\\Books",
            'minimum' => 10.5,
        ])->toArray()['filters'];

        $this->assertSame("(active:'true') AND (amount>=10.5 AND category:'O\\'Reilly\\\\Books')", $filters);
        $this->assertSame("(active:'true') AND (category:'Books')", $recipe->bind(['category' => 'Books'])->toArray()['filters']);

        $operators = [
            'not_equals' => 'NOT amount:10',
            'gt' => 'amount>10',
            'lt' => 'amount<10',
            'lte' => 'amount<=10',
        ];

        foreach ($operators as $operator => $expected) {
            $operatorRecipe = QueryRecipe::fromArray([
                ...$this->definition('events', [
                    'widget' => 'kpi',
                    'date_field' => 'occurred_at',
                    'metric' => 'count',
                ], [[
                    'name' => 'value',
                    'target' => null,
                    'type' => 'integer',
                    'required' => true,
                ]]),
                'filter_templates' => [[
                    'field' => 'amount',
                    'operator' => $operator,
                    'slot' => 'value',
                ]],
            ]);

            $this->assertSame($expected, $operatorRecipe->bind(['value' => 10])->toArray()['filters']);
        }
    }

    /** @test */
    public function it_rejects_malformed_recipe_definitions(): void
    {
        $template = [
            'widget' => 'kpi',
            'date_field' => 'occurred_at',
            'metric' => 'count',
        ];

        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray($this->definition('bad dataset', $template)),
            'Invalid query recipe dataset',
        );
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray($this->definition('events', $template, [[
                'name' => 'Bad-Name',
                'target' => null,
                'type' => 'string',
            ]])),
            'Invalid query recipe slot name',
        );
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray($this->definition('events', $template, [[
                'name' => 'value',
                'target' => null,
                'type' => 'object',
            ]])),
            'Unsupported query recipe slot type',
        );
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray($this->definition('events', [...$template, 'unsupported' => 'fixed'], [[
                'name' => 'value',
                'target' => 'unsupported',
                'type' => 'string',
            ]])),
            'Unsupported query recipe slot target',
        );
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray($this->definition('events', [...$template, 'interval' => 'day'], [
                ['name' => 'first', 'target' => 'interval', 'type' => 'string'],
                ['name' => 'second', 'target' => 'interval', 'type' => 'string'],
            ])),
            'Duplicate query recipe slot target',
        );
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray($this->definition('events', $template, [
                ['name' => 'same', 'target' => null, 'type' => 'string'],
                ['name' => 'same', 'target' => null, 'type' => 'string'],
            ])),
            'Duplicate query recipe slot',
        );
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray([
                ...$this->definition('events', $template, [['name' => 'value', 'target' => null, 'type' => 'string']]),
                'filter_templates' => [['field' => '', 'operator' => 'equals', 'slot' => 'value']],
            ]),
            'filter field is required',
        );
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray([
                ...$this->definition('events', $template, [['name' => 'value', 'target' => null, 'type' => 'string']]),
                'filter_templates' => [['field' => 'category', 'operator' => 'contains', 'slot' => 'value']],
            ]),
            'Unsupported query recipe filter operator',
        );
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray([
                ...$this->definition('events', $template),
                'filter_templates' => [['field' => 'category', 'operator' => 'equals', 'slot' => 'missing']],
            ]),
            'filter references unknown slot',
        );
    }

    /** @test */
    public function it_rejects_invalid_typed_binding_values(): void
    {
        $template = [
            'widget' => 'kpi',
            'date_field' => 'occurred_at',
            'metric' => 'count',
        ];
        $integer = QueryRecipe::fromArray($this->definition('events', $template, [[
            'name' => 'value',
            'target' => null,
            'type' => 'integer',
            'minimum' => 1,
            'maximum' => 10,
        ]]));
        $number = QueryRecipe::fromArray($this->definition('events', $template, [[
            'name' => 'value',
            'target' => null,
            'type' => 'number',
        ]]));
        $string = QueryRecipe::fromArray($this->definition('events', $template, [[
            'name' => 'value',
            'target' => null,
            'type' => 'string',
        ]]));

        $this->assertInvalid(fn (): AnalyticsRequest => $integer->bind(['value' => 'many']), 'must be an integer');
        $this->assertInvalid(fn (): AnalyticsRequest => $integer->bind(['value' => 11]), 'outside its allowed range');
        $this->assertInvalid(fn (): AnalyticsRequest => $number->bind(['value' => 'many']), 'must be numeric');
        $this->assertInvalid(fn (): AnalyticsRequest => $string->bind(['value' => ' ']), 'cannot be empty');
    }

    /** @test */
    public function it_rejects_mapping_and_grouped_metric_contract_mismatches(): void
    {
        $index = $this->recipeIndex();
        $nested = QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'table',
            'date_field' => 'occurred_at',
            'fields' => 'meta.label,amount',
        ]));

        $this->assertSame(QueryRecipe::contractFingerprint($index), QueryRecipe::contractFingerprint($index));
        $this->assertSame($nested, $nested->validateAgainst($index));

        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray($this->definition('events', [
                'widget' => 'table',
                'date_field' => 'occurred_at',
                'fields' => 'category,amount',
                'sort' => 'amount:sideways',
            ]))->validateAgainst($index),
            'Unsupported query recipe sort direction',
        );
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray($this->definition('events', [
                'widget' => 'grouped_metrics',
                'date_field' => 'occurred_at',
                'group_by' => 'category',
                'metrics' => 'invalid',
            ]))->validateAgainst($index),
            'grouped metrics must be valid JSON',
        );
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray($this->definition('events', [
                'widget' => 'grouped_metrics',
                'date_field' => 'occurred_at',
                'group_by' => 'category',
                'metrics' => '[1]',
            ]))->validateAgainst($index),
            'grouped metric must be an object',
        );
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray($this->definition('events', [
                'widget' => 'kpi',
                'date_field' => 'occurred_at',
                'metric' => 'avg',
                'field' => 'missing',
            ]))->validateAgainst($index),
            'field [missing] does not exist',
        );
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray($this->definition('events', [
                'widget' => 'kpi',
                'date_field' => 'occurred_at',
                'metric' => 'avg',
                'field' => 'category',
            ]))->validateAgainst($index),
            'field [category] has incompatible type',
        );
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
                $properties->object('meta', function (NewProperties $meta): void {
                    $meta->keyword('label');
                });

                return $properties;
            }
        };
        $index->create();

        return $index;
    }

    private function assertInvalid(callable $callback, string $message): void
    {
        try {
            $callback();
            $this->fail('Expected an InvalidArgumentException containing: '.$message);
        } catch (InvalidArgumentException $invalidArgumentException) {
            $this->assertStringContainsString($message, $invalidArgumentException->getMessage());
        }
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
