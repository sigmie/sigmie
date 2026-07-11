<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use InvalidArgumentException;
use ReflectionClass;
use Sigmie\Analytics\AnalyticsRequest;
use Sigmie\Analytics\QueryRecipe;
use Sigmie\Mappings\NewProperties;
use Sigmie\Parse\FilterParser;
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
            'distribution' => [...$base, 'widget' => 'distribution', 'field' => 'amount', 'bucket_size' => 10],
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
