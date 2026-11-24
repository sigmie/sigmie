<?php

declare(strict_types=1);

namespace Sigmie\Tests;

require_once __DIR__.'/Stubs/LaravelAiStubs.php';

use InvalidArgumentException;
use Laravel\Ai\Tools\Request;
use ReflectionClass;
use Sigmie\AI\SigmieAnalyticsTool;
use Sigmie\Analytics\AnalyticsRequest;
use Sigmie\Analytics\QueryRecipe;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Parse\FilterParser;
use Sigmie\Sigmie;
use Sigmie\SigmieIndex;
use Sigmie\Testing\TestCase;

class QueryRecipeTest extends TestCase
{
    public function test_integral_json_numbers_bind_to_integer_slots(): void
    {
        $definition = $this->definition('events', [
            'widget' => 'table', 'date_field' => 'date',
        ], [[
            'name' => 'rows', 'target' => 'limit', 'type' => 'integer',
            'minimum' => 1.0, 'maximum' => 3.0, 'default' => 2.0,
        ]]);

        $recipe = QueryRecipe::fromArray($definition);

        $this->assertSame(2, $recipe->bind([])->toArray()['limit']);
        $this->assertSame(3, $recipe->bind(['rows' => 3.0])->toArray()['limit']);
        $this->assertInvalid(fn () => $recipe->bind(['rows' => 2.5]), 'Query recipe binding [rows] must be an integer.');
    }

    public function test_explicit_slot_defaults_are_validated_as_request_arguments(): void
    {
        $this->assertInvalid(fn () => QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'table', 'date_field' => 'date', 'limit' => 5,
        ], [[
            'name' => 'rows', 'target' => 'limit', 'type' => 'integer', 'default' => 0,
        ]])), 'Analytics argument [limit] must be between 1 and 100.');
    }

    public function test_recipe_rejects_a_nested_timeline_that_the_tool_cannot_query(): void
    {
        $properties = new NewProperties;
        $properties->nested('events', fn (NewProperties $events) => $events->datetime('occurred_at'));
        $recipe = QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'trend', 'date_field' => 'events.occurred_at', 'metric' => 'count',
        ]));

        $this->assertInvalid(
            fn () => $recipe->validateAgainst($this->eventsIndex($properties)),
            'Query recipe date_field [events.occurred_at] must be outside nested documents.',
        );
    }

    public function test_widget_names_are_normalized_before_recipe_defaults(): void
    {
        $template = [
            'widget' => 'breakdown', 'date_field' => 'date', 'metric' => 'count', 'group_by' => 'category',
        ];
        $plain = QueryRecipe::fromArray($this->definition('events', $template));
        $padded = QueryRecipe::fromArray($this->definition('events', [...$template, 'widget' => ' breakdown ']));

        $this->assertSame($plain->toArray(), $padded->toArray());
        $this->assertSame(20, $padded->bind(['limit' => 20])->toArray()['limit']);
    }

    public function test_histogram_count_recipes_accept_non_numeric_metric_fields(): void
    {
        $props = new NewProperties;
        $props->date('date');
        $props->number('amount');
        $props->keyword('status');
        $recipe = QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'histogram_metric', 'date_field' => 'date', 'metric' => 'count',
            'field' => 'status', 'bucket_field' => 'amount', 'bucket_size' => 50,
        ]));

        $this->assertSame($recipe, $recipe->validateAgainst($this->eventsIndex($props)));
    }

    public function test_percentile_recipes_accept_date_fields(): void
    {
        $props = new NewProperties;
        $props->date('date');
        $recipe = QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'percentiles', 'date_field' => 'date', 'field' => 'date',
        ]));

        $this->assertSame($recipe, $recipe->validateAgainst($this->eventsIndex($props)));
    }

    public function test_bound_keyword_filters_preserve_literal_padding(): void
    {
        $props = new NewProperties;
        $props->date('date');
        $props->caseSensitiveKeyword('code');
        $definition = $this->definition('events', [
            'widget' => 'kpi', 'date_field' => 'date', 'metric' => 'count',
        ], [['name' => 'code', 'type' => 'string', 'default' => ' A ']]);
        $definition['filter_templates'] = [['field' => 'code', 'operator' => 'equals', 'slot' => 'code']];
        $recipe = QueryRecipe::fromArray($definition)->validateAgainst($this->eventsIndex($props));

        foreach ([[[], ' A '], [['code' => ' B '], ' B ']] as [$bindings, $expected]) {
            $filter = $recipe->bind($bindings)->toArray()['filters'];
            $raw = (new FilterParser($props))->parse($filter)->toRaw();

            $this->assertSame($expected, $raw['bool']['must'][0]['term']['code']['value']);
        }
    }

    protected function setUp(): void
    {
        if ($this->runsWithoutCluster()) {
            return;
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        if ($this->runsWithoutCluster()) {
            return;
        }

        parent::tearDown();
    }

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
    public function it_adds_result_limit_slots_only_to_widgets_that_use_them_or_templates_that_declare_them(): void
    {
        $kpi = QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'kpi',
            'date_field' => 'occurred_at',
            'metric' => 'count',
        ]));
        $breakdown = QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'breakdown',
            'date_field' => 'occurred_at',
            'metric' => 'count',
            'group_by' => 'category',
        ]));

        $this->assertSame([], $kpi->toArray()['slots']);
        $this->assertSame('limit', $breakdown->toArray()['slots'][0]['target']);
    }

    /** @test */
    public function it_canonicalizes_ranked_widget_sort_as_metric_direction(): void
    {
        $legacy = QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'breakdown',
            'date_field' => 'occurred_at',
            'metric' => 'count',
            'field' => 'occurred_at',
            'group_by' => 'category',
            'sort' => 'occurred_at:asc',
        ]));
        $default = QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'union_breakdown',
            'date_field' => 'occurred_at',
            'metric' => 'count',
            'field' => 'occurred_at',
            'group_by_fields' => 'category,subcategory',
        ]));

        $this->assertSame('metric:asc', $legacy->toArray()['template']['sort']);
        $this->assertSame('metric:desc', $default->toArray()['template']['sort']);
        $this->assertSame('metric:asc', $legacy->bind([])->toArray()['sort']);
    }

    /** @test */
    public function it_promotes_limit_defaults_without_colliding_with_declared_slot_names(): void
    {
        $recipe = QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'trend',
            'date_field' => 'occurred_at',
            'metric' => 'avg',
            'field' => 'amount',
            'limit' => '25',
        ], [[
            'name' => 'limit',
            'target' => null,
            'type' => 'integer',
            'default' => '3',
        ]]));

        $slots = array_column($recipe->toArray()['slots'], null, 'name');

        $this->assertSame(3, $slots['limit']['default']);
        $this->assertSame('limit', $slots['result_limit']['target']);
        $this->assertSame(25, $slots['result_limit']['default']);
        $this->assertSame(25, $recipe->bind([])->toArray()['limit']);
        $this->assertSame(30, $recipe->bind(['result_limit' => '30'])->toArray()['limit']);
    }

    /** @test */
    public function it_promotes_a_template_limit_to_an_explicit_target_slot_default(): void
    {
        $recipe = QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'trend',
            'date_field' => 'occurred_at',
            'metric' => 'avg',
            'field' => 'amount',
            'limit' => '25',
        ], [[
            'name' => 'result_limit',
            'target' => 'limit',
            'type' => 'integer',
        ]]));

        $this->assertSame(25, $recipe->toArray()['slots'][0]['default']);
        $this->assertSame(25, $recipe->bind([])->toArray()['limit']);
    }

    /** @test */
    public function numeric_recipe_filters_keep_the_supplied_precision(): void
    {
        $definition = $this->definition('events', [
            'widget' => 'kpi',
            'date_field' => 'occurred_at',
            'metric' => 'count',
        ], [['name' => 'threshold', 'type' => 'number']]);
        $definition['filter_templates'][] = ['field' => 'amount', 'operator' => 'gte', 'slot' => 'threshold'];
        $recipe = QueryRecipe::fromArray($definition);

        $this->assertSame('amount>=9007199254740993', $recipe->bind(['threshold' => 9007199254740993])->toArray()['filters']);
        $this->assertSame('amount>=9007199254740993', $recipe->bind(['threshold' => '9007199254740993'])->toArray()['filters']);
        $this->assertSame('amount>=1.2345678901234567', $recipe->bind(['threshold' => 1.2345678901234567])->toArray()['filters']);
    }

    /** @test */
    public function a_recipe_accepts_the_sort_parsers_geo_coordinates(): void
    {
        $props = new NewProperties;
        $props->date('occurred_at');
        $props->geoPoint('location');
        $index = $this->eventsIndex($props);
        $sort = 'location[+.5,-.75]:m:asc';

        $recipe = QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'table',
            'date_field' => 'occurred_at',
            'sort' => $sort,
            'hit_sort' => '_score '.$sort,
            'include_hits' => 1,
        ]));

        $bound = $recipe->validateAgainst($index)->bind([])->toArray();

        $this->assertSame($sort, $bound['sort']);
        $this->assertSame('_score '.$sort, $bound['hit_sort']);
    }

    /** @test */
    public function a_grouped_metric_recipe_accepts_trimmed_specs(): void
    {
        $recipe = QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'grouped_metrics',
            'date_field' => 'occurred_at',
            'group_by' => 'category',
            'metrics' => [['key' => 'total', 'metric' => ' sum ', 'field' => ' amount ']],
        ]));

        $this->assertSame($recipe, $recipe->validateAgainst($this->recipeIndex()));
    }

    /** @test */
    public function a_recipe_accepts_distinct_date_values(): void
    {
        $index = $this->recipeIndex();
        $tool = new SigmieAnalyticsTool($index);
        $base = ['date_field' => 'occurred_at', 'from' => '2024-01-01', 'to' => '2024-01-02'];
        $index->merge([
            new Document(['occurred_at' => '2024-01-01T00:00:00Z', 'category' => 'a']),
            new Document(['occurred_at' => '2024-01-01T01:00:00Z', 'category' => 'a']),
        ], refresh: true);

        $kpi = QueryRecipe::fromArray($this->definition('events', [
            ...$base, 'widget' => 'kpi', 'metric' => 'unique', 'field' => 'occurred_at',
        ]));
        $grouped = QueryRecipe::fromArray($this->definition('events', [
            ...$base, 'widget' => 'grouped_metrics', 'group_by' => 'category',
            'metrics' => [['key' => 'dates', 'metric' => 'unique', 'field' => 'occurred_at']],
        ]));

        $this->assertEquals(2, $tool->result(new Request($kpi->validateAgainst($index)->bind([])->toArray()))['value']);
        $this->assertEquals(2, $tool->result(new Request($grouped->validateAgainst($index)->bind([])->toArray()))['rows'][0]['value']);
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

        $multi = QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'multi_breakdown',
            'date_field' => 'occurred_at',
            'metric' => 'count',
            'group_by_fields' => ['year', 'active'],
        ]));

        $this->assertSame($multi, $multi->validateAgainst($index));
    }

    /** @test */
    public function it_validates_union_breakdown_fields_and_promotes_its_limit(): void
    {
        $index = $this->recipeIndex();
        $recipe = QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'union_breakdown',
            'date_field' => 'occurred_at',
            'metric' => 'sum',
            'field' => 'amount',
            'group_by_fields' => ['category', 'subcategory'],
            'limit' => 5,
        ]));

        $this->assertSame($recipe, $recipe->validateAgainst($index));
        $this->assertSame(8, $recipe->bind(['limit' => 8])->toArray()['limit']);

        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray($this->definition('events', [
                'widget' => 'union_breakdown',
                'date_field' => 'occurred_at',
                'metric' => 'count',
                'group_by_fields' => ['category', 'year'],
            ]))->validateAgainst($index),
            'union_breakdown group_by_fields must have compatible types',
        );
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
    public function it_keeps_asterisks_in_equality_bindings_literal(): void
    {
        $recipe = QueryRecipe::fromArray([
            ...$this->definition('events', [
                'widget' => 'kpi',
                'date_field' => 'occurred_at',
                'metric' => 'count',
            ], [[
                'name' => 'category',
                'target' => null,
                'type' => 'string',
                'required' => true,
            ]]),
            'filter_templates' => [[
                'field' => 'category',
                'operator' => 'equals',
                'slot' => 'category',
            ]],
        ]);

        $filter = $recipe->bind(['category' => 'A*B'])->toArray()['filters'];
        $query = (new FilterParser($this->recipeIndex()->properties()))->parse($filter)->toRaw();

        $this->assertSame("category:'A\\*B'", $filter);
        $this->assertSame('A*B', $query['bool']['must'][0]['term']['category']['value']);
        $this->assertArrayNotHasKey('wildcard', $query['bool']['must'][0]);
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
            fn (): QueryRecipe => QueryRecipe::fromArray($this->definition('events', $template, [[
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
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray([
                'dataset' => 'events',
                'template' => $template,
                'slots' => 'malformed',
                'filter_templates' => [],
            ]),
            'slots must be a list of arrays',
        );
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray([
                'dataset' => 'events',
                'template' => $template,
                'slots' => [['name' => 'value', 'target' => null, 'type' => 'string']],
                'filter_templates' => ['malformed'],
            ]),
            'filter_templates item must be an object',
        );
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray([
                'dataset' => 'events',
                'template' => [...$template, 'unknown' => true],
                'slots' => [],
                'filter_templates' => [],
            ]),
            'Unknown analytics arguments: unknown',
        );
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray([
                ...$this->definition('events', $template),
                'filter_template' => [],
            ]),
            'Unknown query recipe definition keys: filter_template',
        );
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray($this->definition('events', [
                'widget' => 'table',
                'date_field' => 'occurred_at',
                'sort' => 'amount:desc',
            ], [[
                'name' => 'sort',
                'target' => 'sort',
                'type' => 'string',
            ]])),
            'Unsupported query recipe slot target [sort]',
        );
    }

    /** @test */
    public function it_rejects_invalid_raw_definition_shapes(): void
    {
        $template = [
            'widget' => 'kpi',
            'date_field' => 'occurred_at',
            'metric' => 'count',
        ];
        $definition = $this->definition('events', $template);
        $invalidDefinitions = [
            [[...$definition, 'version' => 2], 'Unsupported query recipe version'],
            [[...$definition, 'dataset' => []], 'Query recipe dataset must be a string'],
            [[...$definition, 'template' => 'malformed'], 'Query recipe template must be an array'],
            [[...$definition, 'slots' => [['name' => 1]]], 'Query recipe slot name must be a string'],
            [[...$definition, 'slots' => [['type' => 1]]], 'Query recipe slot type must be a string'],
            [[...$definition, 'slots' => [['target' => 1]]], 'Query recipe slot target must be a string or null'],
            [[...$definition, 'slots' => [['required' => 1]]], 'Query recipe slot required must be a boolean'],
            [[...$definition, 'filter_templates' => [['field' => 1]]], 'Query recipe filter template field must be a string'],
            [[...$definition, 'filter_templates' => [['operator' => 1]]], 'Query recipe filter template operator must be a string'],
            [[...$definition, 'filter_templates' => [['slot' => 1]]], 'Query recipe filter template slot must be a string'],
            [[...$definition, 'slots' => [['unexpected' => true]]], 'Unknown query recipe slots keys: unexpected'],
            [[...$definition, 'filter_templates' => [['unexpected' => true]]], 'Unknown query recipe filter_templates keys: unexpected'],
            [[...$definition, 'slots' => [[
                'name' => 'value',
                'target' => null,
                'type' => 'object',
                'default' => 'fallback',
            ]]], 'Unsupported query recipe slot type'],
        ];

        foreach ($invalidDefinitions as [$invalidDefinition, $message]) {
            $this->assertInvalid(
                fn (): QueryRecipe => QueryRecipe::fromArray($invalidDefinition),
                $message,
            );
        }
    }

    /** @test */
    public function it_accepts_definitions_without_optional_lists(): void
    {
        $recipe = QueryRecipe::fromArray([
            'dataset' => 'events',
            'template' => [
                'widget' => 'kpi',
                'date_field' => 'occurred_at',
                'metric' => 'count',
            ],
        ]);

        $this->assertSame([], $recipe->toArray()['slots']);
        $this->assertSame([], $recipe->toArray()['filter_templates']);
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
            'minimum' => 1.5,
            'maximum' => 10.5,
        ]]));
        $string = QueryRecipe::fromArray($this->definition('events', $template, [[
            'name' => 'value',
            'target' => null,
            'type' => 'string',
        ]]));

        $this->assertInvalid(fn (): AnalyticsRequest => $integer->bind(['value' => 'many']), 'must be an integer');
        $this->assertInvalid(fn (): AnalyticsRequest => $integer->bind(['value' => 1.5]), 'must be an integer');
        $this->assertInvalid(fn (): AnalyticsRequest => $integer->bind(['value' => '1e1']), 'must be an integer');
        $this->assertInvalid(fn (): AnalyticsRequest => $integer->bind(['value' => 11]), 'outside its allowed range');
        $this->assertInvalid(fn (): AnalyticsRequest => $number->bind(['value' => 'many']), 'must be numeric');
        $this->assertInvalid(fn (): AnalyticsRequest => $number->bind(['value' => '1e309']), 'must be finite');
        $this->assertInvalid(fn (): AnalyticsRequest => $number->bind(['value' => 11]), 'outside its allowed range');
        $this->assertInvalid(fn (): AnalyticsRequest => $string->bind(['value' => ' ']), 'cannot be empty');
    }

    /** @test */
    public function it_rejects_non_scalar_typed_bindings(): void
    {
        $template = [
            'widget' => 'kpi',
            'date_field' => 'occurred_at',
            'metric' => 'count',
        ];
        $string = QueryRecipe::fromArray($this->definition('events', $template, [[
            'name' => 'value',
            'target' => null,
            'type' => 'string',
        ]]));
        $number = QueryRecipe::fromArray($this->definition('events', $template, [[
            'name' => 'value',
            'target' => null,
            'type' => 'number',
        ]]));

        $this->assertInvalid(fn (): AnalyticsRequest => $string->bind(['value' => []]), 'must be a string');
        $this->assertInvalid(fn (): AnalyticsRequest => $number->bind(['value' => []]), 'must be numeric');
    }

    /** @test */
    public function it_canonicalizes_typed_defaults_and_rejects_invalid_dates_and_bounds(): void
    {
        $template = [
            'widget' => 'kpi',
            'date_field' => 'occurred_at',
            'metric' => 'count',
        ];
        $recipe = QueryRecipe::fromArray($this->definition('events', $template, [
            ['name' => 'day', 'target' => null, 'type' => 'date', 'default' => '2026-01-02'],
            ['name' => 'minimum', 'target' => null, 'type' => 'number', 'default' => '2.5', 'minimum' => '1.5', 'maximum' => '3.5'],
        ]));
        $slots = array_column($recipe->toArray()['slots'], null, 'name');

        $this->assertSame('2026-01-02', $slots['day']['default']);
        $this->assertSame(2.5, $slots['minimum']['default']);
        $this->assertSame(1.5, $slots['minimum']['minimum']);
        $this->assertSame(3.5, $slots['minimum']['maximum']);

        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray($this->definition('events', $template, [[
                'name' => 'day',
                'target' => null,
                'type' => 'date',
                'default' => 'tomorrow',
            ]])),
            'must be a valid ISO 8601 date',
        );
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray($this->definition('events', $template, [[
                'name' => 'value',
                'target' => null,
                'type' => 'integer',
                'minimum' => 10,
                'maximum' => 1,
            ]])),
            'minimum cannot exceed its maximum',
        );
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray($this->definition('events', $template, [[
                'name' => 'offset',
                'target' => 'timezone_offset',
                'type' => 'timezone_offset',
                'minimum' => -841,
            ]])),
            'timezone bounds must stay between -840 and 840',
        );
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
            'grouped_metrics metrics must be a JSON array',
        );
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray($this->definition('events', [
                'widget' => 'grouped_metrics',
                'date_field' => 'occurred_at',
                'group_by' => 'category',
                'metrics' => '[1]',
            ]))->validateAgainst($index),
            'grouped_metrics metric must be an object',
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
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray($this->definition('events', [
                'widget' => 'kpi',
                'date_field' => 'occurred_at',
                'metric' => 'count',
                'filters' => "missing:'value'",
            ]))->validateAgainst($index),
            'Invalid query recipe filters',
        );
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray($this->definition('events', [
                'widget' => 'funnel',
                'date_field' => 'occurred_at',
                'steps' => [['label' => 'Missing', 'filter' => "missing:'value'"]],
            ]))->validateAgainst($index),
            'Invalid query recipe funnel step filter',
        );
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray($this->definition('events', [
                'widget' => 'kpi',
                'date_field' => 'occurred_at',
                'metric' => 'count',
                'include_hits' => 1,
                'hit_fields' => 'missing',
            ]))->validateAgainst($index),
            'hit_fields field [missing] does not exist',
        );
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray($this->definition('events', [
                'widget' => 'kpi',
                'date_field' => 'occurred_at',
                'metric' => 'count',
                'include_hits' => 1,
                'hit_sort' => 'missing:desc',
            ]))->validateAgainst($index),
            'Invalid query recipe hit_sort',
        );
    }

    /** @test */
    public function it_rejects_invalid_grouped_metrics_and_sort_contracts_during_mapping_validation(): void
    {
        $index = $this->recipeIndex();
        $groupedMetrics = [
            'widget' => 'grouped_metrics',
            'date_field' => 'occurred_at',
            'group_by' => 'category',
        ];
        $table = [
            'widget' => 'table',
            'date_field' => 'occurred_at',
            'fields' => 'category,amount',
        ];

        $this->assertInvalid(
            fn (): QueryRecipe => $this->recipeWithRawTemplate([...$groupedMetrics, 'metrics' => 'invalid'])->validateAgainst($index),
            'grouped metrics must be valid JSON',
        );
        $this->assertInvalid(
            fn (): QueryRecipe => $this->recipeWithRawTemplate([...$groupedMetrics, 'metrics' => '[1]'])->validateAgainst($index),
            'grouped metric must be an object',
        );
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray($this->definition('events', [
                ...$table,
                'sort' => 'amount:desc category:asc',
            ]))->validateAgainst($index),
            'sort must contain exactly one field',
        );
        $this->assertInvalid(
            fn (): QueryRecipe => QueryRecipe::fromArray($this->definition('events', [
                ...$table,
                'sort' => 'amount:desc:extra',
            ]))->validateAgainst($index),
            'Unsupported query recipe sort [amount:desc:extra]',
        );
    }

    /** @test */
    public function unbound_optional_range_keeps_template_dates(): void
    {
        $recipe = QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'kpi',
            'date_field' => 'occurred_at',
            'metric' => 'count',
            'from' => '2024-01-01',
            'to' => '2024-02-01',
        ], [[
            'name' => 'window',
            'target' => 'range',
            'type' => 'period',
            'required' => false,
        ]]));

        $template = $recipe->toArray()['template'];
        $bound = $recipe->bind([])->toArray();

        $this->assertSame('2024-01-01', $template['from']);
        $this->assertSame('2024-02-01', $template['to']);
        $this->assertArrayNotHasKey('range', $template);
        $this->assertSame('2024-01-01', $bound['from']);
        $this->assertSame('2024-02-01', $bound['to']);
        $this->assertArrayNotHasKey('range', $bound);

        $ranged = QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'kpi',
            'date_field' => 'occurred_at',
            'metric' => 'count',
            'range' => 'this_month',
        ], [[
            'name' => 'window_start',
            'target' => 'from',
            'type' => 'date',
            'required' => true,
        ]]));
        $boundFrom = $ranged->bind(['window_start' => '2024-06-01'])->toArray();

        $this->assertSame('this_month', $boundFrom['range']);
        $this->assertArrayNotHasKey('from', $boundFrom);
        $this->assertArrayNotHasKey('to', $boundFrom);
    }

    /** @test */
    public function unique_metric_accepts_keyword_category_and_boolean(): void
    {
        $props = new NewProperties;
        $props->datetime('occurred_at');
        $props->double('amount');
        $props->keyword('customer_id');
        $props->category('product');
        $props->bool('active');
        $index = $this->eventsIndex($props);

        foreach (['customer_id', 'product', 'active', 'amount'] as $field) {
            $recipe = QueryRecipe::fromArray($this->definition('events', [
                'widget' => 'kpi',
                'date_field' => 'occurred_at',
                'metric' => 'unique',
                'field' => $field,
            ]));

            $this->assertSame($recipe, $recipe->validateAgainst($index));
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Query recipe field field [customer_id] has incompatible type [keyword].');

        QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'kpi',
            'date_field' => 'occurred_at',
            'metric' => 'sum',
            'field' => 'customer_id',
        ]))->validateAgainst($index);
    }

    /** @test */
    public function a_csv_field_named_zero_is_checked(): void
    {
        $dated = new NewProperties;
        $dated->datetime('occurred_at');
        $dated->keyword('category');
        $dated->date('0');

        $plain = new NewProperties;
        $plain->datetime('occurred_at');
        $plain->keyword('category');

        $release = new NewProperties;
        $release->datetime('occurred_at');
        $release->keyword('category');
        $release->date('release_date');

        $cases = [
            [$this->eventsIndex($dated), ['widget' => 'union_breakdown', 'date_field' => 'occurred_at', 'metric' => 'count', 'group_by_fields' => '0,category']],
            [$this->eventsIndex($release), ['widget' => 'union_breakdown', 'date_field' => 'occurred_at', 'metric' => 'count', 'group_by_fields' => 'release_date,category']],
            [$this->eventsIndex($plain), ['widget' => 'table', 'date_field' => 'occurred_at', 'fields' => '0']],
            [$this->eventsIndex($plain), ['widget' => 'table', 'date_field' => 'occurred_at', 'fields' => 'nope']],
        ];
        $messages = [];

        foreach ($cases as [$index, $template]) {
            try {
                QueryRecipe::fromArray($this->definition('events', $template))->validateAgainst($index);
                $messages[] = 'returned';
            } catch (InvalidArgumentException $exception) {
                $messages[] = $exception->getMessage();
            }
        }

        $this->assertSame([
            'Query recipe group_by_fields field [0] has incompatible type [date].',
            'Query recipe group_by_fields field [release_date] has incompatible type [date].',
            'Query recipe fields field [0] does not exist in the index.',
            'Query recipe fields field [nope] does not exist in the index.',
        ], $messages);
    }

    /** @test */
    public function a_field_named_one_stays_beside_a_nested_field(): void
    {
        $props = new NewProperties;
        $props->datetime('occurred_at');
        $props->number('1');
        $props->nested('items', function (NewProperties $props): void {
            $props->keyword('sku');
        });
        $index = $this->eventsIndex($props);

        $sum = QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'kpi',
            'date_field' => 'occurred_at',
            'metric' => 'sum',
            'field' => '1',
        ]));
        $nested = QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'table',
            'date_field' => 'occurred_at',
            'fields' => 'items.sku',
        ]));

        $this->assertSame($sum, $sum->validateAgainst($index));
        $this->assertSame($nested, $nested->validateAgainst($index));
    }

    /** @test */
    public function an_optional_slot_inherits_its_template_value(): void
    {
        $recipe = QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'trend',
            'date_field' => 'occurred_at',
            'metric' => 'count',
            'interval' => 'week',
        ], [['name' => 'period', 'target' => 'interval', 'type' => 'string']]));
        $recipe = QueryRecipe::fromArray($recipe->toArray());

        $this->assertSame('week', $recipe->bind([])->toArray()['interval'] ?? null);
        $this->assertSame('day', $recipe->bind(['period' => 'day'])->toArray()['interval']);
    }

    /** @test */
    public function grouped_unique_recipes_accept_keyword_fields(): void
    {
        $props = new NewProperties;
        $props->date('occurred_at');
        $props->keyword('category');
        $props->keyword('user');
        $recipe = QueryRecipe::fromArray($this->definition('events', [
            'widget' => 'grouped_metrics',
            'date_field' => 'occurred_at',
            'group_by' => 'category',
            'metrics' => [['key' => 'users', 'metric' => 'unique', 'field' => 'user']],
        ]));

        $this->assertSame($recipe, $recipe->validateAgainst($this->eventsIndex($props)));
    }

    /** @test */
    public function a_bound_nested_filter_targets_nested_documents(): void
    {
        $props = new NewProperties;
        $props->date('occurred_at');
        $props->nested('items', fn (NewProperties $items) => $items->keyword('sku'));
        $definition = $this->definition('events', [
            'widget' => 'kpi',
            'date_field' => 'occurred_at',
            'metric' => 'count',
        ], [['name' => 'sku', 'type' => 'string']]);
        $definition['filter_templates'][] = ['field' => 'items.sku', 'operator' => 'equals', 'slot' => 'sku'];
        $recipe = QueryRecipe::fromArray($definition)->validateAgainst($this->eventsIndex($props));
        $filters = $recipe->bind(['sku' => 'A'])->toArray()['filters'];
        $query = (new FilterParser($props))->parse($filters)->toRaw();

        $nested = $query['bool']['must'][0]['nested'] ?? [];
        $this->assertSame('items', $nested['path'] ?? null);
        $this->assertStringContainsString('"items.sku"', json_encode($nested['query']));
    }

    public function test_contract_identity_ignores_field_declaration_order(): void
    {
        $first = new NewProperties;
        $first->date('01');
        $first->keyword('1');
        $second = new NewProperties;
        $second->keyword('1');
        $second->date('01');

        $this->assertSame(QueryRecipe::contractFingerprint($this->eventsIndex($first)),
            QueryRecipe::contractFingerprint($this->eventsIndex($second)));
    }

    public function test_percentile_slot_inherits_a_list_from_the_template(): void
    {
        $recipe = QueryRecipe::fromArray($this->definition('sales', [
            'widget' => 'percentiles', 'date_field' => 'date', 'field' => 'amount', 'percents' => [50, 97.5],
        ], [['name' => 'points', 'type' => 'string', 'target' => 'percents']]));

        $this->assertSame('50,97.5', $recipe->bind([])->toArray()['percents']);
        $this->assertSame('25,75', $recipe->bind(['points' => '25,75'])->toArray()['percents']);
    }

    public function test_recipes_can_group_by_date_terms(): void
    {
        $properties = new NewProperties;
        $properties->date('day');
        $properties->keyword('region');
        $index = $this->eventsIndex($properties);
        foreach ([
            ['widget' => 'breakdown', 'group_by' => 'day'],
            ['widget' => 'multi_breakdown', 'group_by_fields' => ['day', 'region']],
        ] as $widget) {
            $recipe = QueryRecipe::fromArray($this->definition('sales', ['date_field' => 'day', 'metric' => 'count', ...$widget]));
            $this->assertSame($recipe, $recipe->validateAgainst($index));
        }
    }

    public function test_recipes_accept_date_extrema_and_medians(): void
    {
        $properties = new NewProperties;
        $properties->date('date');
        $properties->keyword('group');
        $index = $this->eventsIndex($properties);

        foreach (['min', 'max', 'median'] as $metric) {
            $spec = ['field' => 'date', 'metric' => $metric];
            foreach ([
                ['widget' => 'kpi', ...$spec],
                ['widget' => 'grouped_metrics', 'group_by' => 'group', 'metrics' => [['key' => 'when', ...$spec]]],
            ] as $template) {
                $recipe = QueryRecipe::fromArray($this->definition('sales', ['date_field' => 'date', ...$template]));
                $this->assertSame($recipe, $recipe->validateAgainst($index));
            }
        }
    }

    private function runsWithoutCluster(): bool
    {
        return in_array($this->getName(false), [
            'test_integral_json_numbers_bind_to_integer_slots',
            'test_explicit_slot_defaults_are_validated_as_request_arguments',
            'test_recipe_rejects_a_nested_timeline_that_the_tool_cannot_query',
            'test_contract_identity_ignores_field_declaration_order',
            'test_percentile_slot_inherits_a_list_from_the_template',
            'test_recipes_can_group_by_date_terms',
            'test_recipes_accept_date_extrema_and_medians',
            'numeric_recipe_filters_keep_the_supplied_precision',
            'a_recipe_accepts_the_sort_parsers_geo_coordinates',
            'an_optional_slot_inherits_its_template_value',
            'grouped_unique_recipes_accept_keyword_fields',
            'a_bound_nested_filter_targets_nested_documents',
            'unbound_optional_range_keeps_template_dates',
            'unique_metric_accepts_keyword_category_and_boolean',
            'a_csv_field_named_zero_is_checked',
            'a_field_named_one_stays_beside_a_nested_field',
            'test_widget_names_are_normalized_before_recipe_defaults',
            'test_histogram_count_recipes_accept_non_numeric_metric_fields',
            'test_percentile_recipes_accept_date_fields',
            'test_bound_keyword_filters_preserve_literal_padding',
        ], true);
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

    private function eventsIndex(NewProperties $props): SigmieIndex
    {
        return new class($props) extends SigmieIndex
        {
            public function __construct(private NewProperties $props) {}

            public function name(): string
            {
                return 'events';
            }

            public function properties(): NewProperties
            {
                return $this->props;
            }
        };
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

    /** @param array<string, mixed> $template */
    private function recipeWithRawTemplate(array $template): QueryRecipe
    {
        $reflection = new ReflectionClass(QueryRecipe::class);
        $recipe = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('definition')->setValue($recipe, [
            'version' => 1,
            'dataset' => 'events',
            'template' => $template,
            'slots' => [],
            'filter_templates' => [],
        ]);

        return $recipe;
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
            'union_breakdown' => [...$base, 'widget' => 'union_breakdown', 'metric' => 'sum', 'field' => 'amount', 'group_by_fields' => 'category,subcategory'],
            'distribution' => [...$base, 'widget' => 'distribution', 'field' => 'amount', 'bucket_size' => 10],
            'histogram_metric' => [...$base, 'widget' => 'histogram_metric', 'metric' => 'avg', 'field' => 'amount', 'bucket_field' => 'amount', 'bucket_size' => 10],
            'grouped_metrics' => [...$base, 'widget' => 'grouped_metrics', 'group_by' => 'category', 'metrics' => '[{"key":"count","label":"Count","metric":"count"},{"key":"avg_amount","label":"Average amount","metric":"avg","field":"amount"}]'],
            'percentiles' => [...$base, 'widget' => 'percentiles', 'field' => 'amount', 'percents' => '50,95'],
            'stats' => [...$base, 'widget' => 'stats', 'field' => 'amount'],
            'table' => [...$base, 'widget' => 'table', 'fields' => 'category,amount', 'sort' => 'amount:desc', 'hit_sort' => '_score location[52.49,13.77]:m:asc'],
            'funnel' => [...$base, 'widget' => 'funnel', 'steps' => '[{"label":"All","filter":"category:\'all\'"}]'],
            'heatmap' => [...$base, 'widget' => 'heatmap', 'metric' => 'sum', 'field' => 'amount', 'row_field' => 'category', 'col_field' => 'subcategory'],
            'retention' => [...$base, 'widget' => 'retention', 'cohort_field' => 'occurred_at', 'id_field' => 'user_id', 'interval' => 'week'],
            'geo' => [...$base, 'widget' => 'geo', 'field' => 'location', 'precision' => 5],
        ];
    }
    public function test_date_filter_slots_reject_invalid_iso_offsets(): void
    {
        $definition = $this->definition('events', [
            'widget' => 'kpi',
            'date_field' => 'occurred_at',
            'metric' => 'count',
        ], [[
            'name' => 'cutoff',
            'type' => 'date',
            'required' => true,
        ]]);
        $definition['filter_templates'] = [['field' => 'occurred_at', 'operator' => 'gte', 'slot' => 'cutoff']];
        $recipe = QueryRecipe::fromArray($definition);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Query recipe binding [cutoff] must be a valid ISO 8601 date.');
        $recipe->bind(['cutoff' => '2024-02-29T12:00:00+25:00']);
    }
}
