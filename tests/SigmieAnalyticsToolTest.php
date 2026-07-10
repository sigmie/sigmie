<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Tests\Stubs\FakeJsonSchema;

require_once __DIR__.'/Stubs/LaravelAiStubs.php';

use DateTimeImmutable;
use DateTimeZone;
use Laravel\Ai\Tools\Request;
use Sigmie\AI\AsTool;
use Sigmie\AI\SigmieAnalyticsTool;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Sigmie;
use Sigmie\SigmieIndex;
use Sigmie\Testing\TestCase;

class SigmieAnalyticsToolTest extends TestCase
{
    private function createSalesIndex(): SigmieIndex
    {
        $index = new class($this->sigmie) extends SigmieIndex
        {
            use AsTool;

            protected string $indexName;

            public function __construct(Sigmie $sigmie)
            {
                parent::__construct($sigmie);

                $this->indexName = uniqid();
            }

            public function name(): string
            {
                return $this->indexName;
            }

            public function properties(): NewProperties
            {
                $props = new NewProperties;
                $props->date('created_at');
                $props->number('amount');
                $props->category('product');
                $props->category('channel');
                $props->object('meta', function (NewProperties $properties): void {
                    $properties->date('observed_at');
                    $properties->number('score');
                });

                return $props;
            }
        };

        $index->create();

        $index->merge([
            new Document(['created_at' => '2024-01-01', 'amount' => 100, 'product' => 'A', 'channel' => 'online']),
            new Document(['created_at' => '2024-01-01', 'amount' => 50, 'product' => 'B', 'channel' => 'online']),
            new Document(['created_at' => '2024-01-02', 'amount' => 200, 'product' => 'A', 'channel' => 'retail']),
            new Document(['created_at' => '2024-01-03', 'amount' => 30, 'product' => 'B', 'channel' => 'retail']),
            new Document(['created_at' => '2024-01-03', 'amount' => 70, 'product' => 'A', 'channel' => 'online']),
        ], refresh: true);

        return $index;
    }

    /**
     * @test
     */
    public function tools_suite_includes_the_analytics_tool(): void
    {
        $index = $this->createSalesIndex();

        $tools = $index->tools();

        $this->assertInstanceOf(SigmieAnalyticsTool::class, $tools[5]);
        $this->assertSame('analytics', $tools[5]->name());
    }

    /**
     * @test
     */
    public function description_lists_widgets_metrics_and_timeline_fields(): void
    {
        $index = $this->createSalesIndex();

        $description = (new SigmieAnalyticsTool($index))->description();

        $this->assertStringContainsString('kpi_delta', $description);
        $this->assertStringContainsString('breakdown', $description);
        $this->assertStringContainsString('cumulative', $description);
        $this->assertStringContainsString('created_at', $description);
        $this->assertStringContainsString('meta.observed_at', $description);
        $this->assertStringContainsString('amount', $description);
        $this->assertStringContainsString('meta.score', $description);
        $this->assertStringContainsString('median', $description);
        $this->assertStringContainsString('include_hits', $description);
        $this->assertStringContainsString('hit_filters', $description);
        $this->assertStringContainsString('bucket_aliases', $description);
        $this->assertStringContainsString('multi_breakdown', $description);
        $this->assertStringContainsString('group_by_fields', $description);
        $this->assertStringContainsString('histogram_metric', $description);
        $this->assertStringContainsString('grouped_metrics', $description);
    }

    /**
     * The 'Choosing a widget' phrasing guide is what stops a small model from reading
     * 'monthly revenue for the last 90 days' as 'one number' (kpi) instead of 'a series
     * bucketed monthly' (trend). Asserts the disambiguation hints are present so they
     * don't quietly drift away in a future edit.
     *
     * @test
     */
    public function description_includes_phrasing_to_widget_disambiguation(): void
    {
        $index = $this->createSalesIndex();

        $description = (new SigmieAnalyticsTool($index))->description();

        // Section header.
        $this->assertStringContainsString('Choosing a widget', $description);

        // The bucket-word trap is named explicitly.
        $this->assertStringContainsString('daily / weekly / monthly / hourly', $description);
        $this->assertStringContainsString('NOT kpi', $description);
        $this->assertStringContainsString('the bucket word means a series', $description);

        // Each widget has a phrasing entry.
        $this->assertStringContainsString('cumulative', $description);
        $this->assertStringContainsString('top N', $description);
        $this->assertStringContainsString('with no bucket word', $description);
        $this->assertStringContainsString('histogram', $description);
        $this->assertStringContainsString('percentiles', $description);
    }

    /**
     * The Examples section gives the model a copy-pasteable argument object per widget, grounded
     * in this index's own fields — so it sees the exact JSON shape (and how `filters` slices one
     * widget) rather than inferring it.
     *
     * @test
     */
    public function description_includes_grounded_argument_examples(): void
    {
        $index = $this->createSalesIndex();

        $description = (new SigmieAnalyticsTool($index))->description();

        $this->assertStringContainsString('Examples (', $description);

        // One example per widget, as valid JSON grounded in the index's real fields.
        foreach (['kpi', 'kpi_delta', 'trend', 'cumulative', 'grouped_trend', 'breakdown', 'multi_breakdown', 'distribution', 'histogram_metric', 'grouped_metrics', 'percentiles', 'stats', 'table', 'funnel', 'heatmap', 'retention', 'geo'] as $widget) {
            $this->assertStringContainsString(sprintf('"widget":"%s"', $widget), $description);
        }

        $this->assertStringContainsString('"date_field":"created_at"', $description);
        $this->assertStringContainsString('"field":"amount"', $description);
        $this->assertStringContainsString('"group_by":"product"', $description);

        // The sliced example shows filters narrowing a single widget.
        $this->assertStringContainsString('"filters"', $description);
    }

    /**
     * @test
     */
    public function schema_marks_required_and_optional_params_for_openai_strict(): void
    {
        $index = $this->createSalesIndex();

        $schema = (new SigmieAnalyticsTool($index))->schema(new FakeJsonSchema);

        foreach ($schema as $name => $prop) {
            $this->assertTrue($prop->required, sprintf("Property '%s' must be required().", $name));
        }

        $this->assertFalse($schema['widget']->nullable, "'widget' must NOT be nullable.");
        $this->assertFalse($schema['date_field']->nullable, "'date_field' must NOT be nullable.");

        foreach (['metric', 'field', 'bucket_field', 'metrics', 'sort_metric', 'min_count', 'interval', 'group_by', 'group_by_fields', 'limit', 'bucket_size', 'percents', 'from', 'to', 'filters', 'bucket_aliases', 'include_hits', 'hit_filters', 'hit_fields', 'hit_sort', 'hit_limit'] as $name) {
            $this->assertTrue($schema[$name]->nullable, sprintf("Optional property '%s' must be nullable().", $name));
        }
    }

    /**
     * @test
     */
    public function result_runs_a_trend_widget(): void
    {
        $index = $this->createSalesIndex();

        $result = (new SigmieAnalyticsTool($index))->result(new Request([
            'widget' => 'trend',
            'date_field' => 'created_at',
            'metric' => 'sum',
            'field' => 'amount',
            'interval' => 'day',
            'from' => '2024-01-01',
            'to' => '2024-01-04',
        ]));

        $this->assertSame('trend', $result['type']);
        $this->assertCount(3, $result['series']);
        $this->assertEquals(150.0, $result['series'][0]['value']);
    }

    /**
     * @test
     */
    public function result_runs_a_fixed_interval_trend_widget(): void
    {
        $index = $this->createSalesIndex();

        $result = (new SigmieAnalyticsTool($index))->result(new Request([
            'widget' => 'trend',
            'date_field' => 'created_at',
            'metric' => 'sum',
            'field' => 'amount',
            'interval' => '12h',
            'from' => '2024-01-01',
            'to' => '2024-01-04',
        ]));

        $this->assertSame('trend', $result['type']);
        $this->assertEquals(150.0, $result['series'][0]['value']);
    }

    /**
     * @test
     */
    public function result_runs_a_breakdown_widget(): void
    {
        $index = $this->createSalesIndex();

        $result = (new SigmieAnalyticsTool($index))->result(new Request([
            'widget' => 'breakdown',
            'date_field' => 'created_at',
            'group_by' => 'product',
            'metric' => 'sum',
            'field' => 'amount',
            'from' => '2024-01-01',
            'to' => '2024-01-04',
        ]));

        $this->assertSame('A', $result['rows'][0]['key']);
        $this->assertEquals(370.0, $result['rows'][0]['value']);
    }

    /**
     * @test
     */
    public function result_merges_breakdown_bucket_aliases(): void
    {
        $index = $this->createSalesIndex();

        $index->merge([
            new Document(['created_at' => '2024-01-02', 'amount' => 250, 'product' => 'B']),
            new Document(['created_at' => '2024-01-02', 'amount' => 180, 'product' => 'C']),
            new Document(['created_at' => '2024-01-02', 'amount' => 170, 'product' => 'Old C']),
        ], refresh: true);

        $result = (new SigmieAnalyticsTool($index))->result(new Request([
            'widget' => 'breakdown',
            'date_field' => 'created_at',
            'group_by' => 'product',
            'metric' => 'sum',
            'field' => 'amount',
            'limit' => 2,
            'from' => '2024-01-01',
            'to' => '2024-01-04',
            'bucket_aliases' => '[{"label":"Combined C","values":["C","Old C"]}]',
        ]));

        $this->assertSame('A', $result['rows'][0]['key']);
        $this->assertSame('Combined C', $result['rows'][1]['key']);
        $this->assertEquals(350.0, $result['rows'][1]['value']);
        $this->assertNotContains('B', array_column($result['rows'], 'key'));
    }

    /**
     * @test
     */
    public function result_runs_a_multi_breakdown_widget(): void
    {
        $index = $this->createSalesIndex();

        $result = (new SigmieAnalyticsTool($index))->result(new Request([
            'widget' => 'multi_breakdown',
            'date_field' => 'created_at',
            'group_by_fields' => 'product,channel',
            'metric' => 'sum',
            'field' => 'amount',
            'limit' => 3,
            'from' => '2024-01-01',
            'to' => '2024-01-04',
        ]));

        $this->assertSame('multi_breakdown', $result['type']);
        $this->assertSame(['A', 'retail'], $result['rows'][0]['key_values']);
        $this->assertEquals(200.0, $result['rows'][0]['value']);
        $this->assertSame(['A', 'online'], $result['rows'][1]['key_values']);
        $this->assertEquals(170.0, $result['rows'][1]['value']);
    }

    /**
     * @test
     */
    public function result_runs_a_histogram_metric_widget(): void
    {
        $index = $this->createSalesIndex();

        $result = (new SigmieAnalyticsTool($index))->result(new Request([
            'widget' => 'histogram_metric',
            'date_field' => 'created_at',
            'metric' => 'avg',
            'field' => 'amount',
            'bucket_field' => 'amount',
            'bucket_size' => 100,
            'from' => '2024-01-01',
            'to' => '2024-01-04',
        ]));

        $this->assertSame('histogram_metric', $result['type']);
        $this->assertEquals(50.0, $result['rows'][0]['value']);
        $this->assertEquals(3, $result['rows'][0]['count']);
    }

    /**
     * @test
     */
    public function result_runs_a_grouped_metrics_widget(): void
    {
        $index = $this->createSalesIndex();

        $result = (new SigmieAnalyticsTool($index))->result(new Request([
            'widget' => 'grouped_metrics',
            'date_field' => 'created_at',
            'group_by' => 'product',
            'metrics' => '[{"key":"count","label":"Count","metric":"count"},{"key":"avg_amount","label":"Average amount","metric":"avg","field":"amount"}]',
            'sort_metric' => 'count',
            'min_count' => 2,
            'from' => '2024-01-01',
            'to' => '2024-01-04',
        ]));

        $this->assertSame('grouped_metrics', $result['type']);
        $this->assertSame('A', $result['rows'][0]['key']);
        $this->assertEquals(3, $result['rows'][0]['metrics']['count']);
        $this->assertEquals(123.33333333333333, $result['rows'][0]['metrics']['avg_amount']);
        $this->assertSame(3, $result['rows'][0]['metric_populations']['avg_amount']['value_count']);
    }

    /**
     * @test
     */
    public function result_applies_bucket_aliases_to_grouped_metrics_before_aggregation(): void
    {
        $index = $this->createSalesIndex();

        $result = (new SigmieAnalyticsTool($index))->result(new Request([
            'widget' => 'grouped_metrics',
            'date_field' => 'created_at',
            'group_by' => 'product',
            'metrics' => '[{"key":"count","label":"Count","metric":"count"},{"key":"avg_amount","label":"Average amount","metric":"avg","field":"amount"}]',
            'sort_metric' => 'count',
            'bucket_aliases' => '[{"label":"Combined","values":["A","B"]}]',
            'from' => '2024-01-01',
            'to' => '2024-01-04',
        ]));

        $this->assertCount(1, $result['rows']);
        $this->assertSame('Combined', $result['rows'][0]['key']);
        $this->assertSame(5, $result['rows'][0]['count']);
        $this->assertEquals(90.0, $result['rows'][0]['metrics']['avg_amount']);
        $this->assertSame(5, $result['rows'][0]['metric_populations']['avg_amount']['value_count']);
    }

    /**
     * @test
     */
    public function result_runs_percentiles_with_default_and_custom_percents(): void
    {
        $index = $this->createSalesIndex();

        $default = (new SigmieAnalyticsTool($index))->result(new Request([
            'widget' => 'percentiles',
            'date_field' => 'created_at',
            'field' => 'amount',
            'from' => '2024-01-01',
            'to' => '2024-01-04',
        ]));

        $this->assertSame('percentiles', $default['type']);
        $this->assertArrayHasKey('50', $default['percentiles']);
        $this->assertArrayHasKey('99', $default['percentiles']);

        $custom = (new SigmieAnalyticsTool($index))->result(new Request([
            'widget' => 'percentiles',
            'date_field' => 'created_at',
            'field' => 'amount',
            'percents' => '25, 90, 100, 0',
            'from' => '2024-01-01',
            'to' => '2024-01-04',
        ]));

        $this->assertSame([25, 90], array_keys($custom['percentiles']));
    }

    /**
     * @test
     */
    public function result_runs_a_count_heatmap_when_metric_is_omitted(): void
    {
        $index = $this->createSalesIndex();

        $result = (new SigmieAnalyticsTool($index))->result(new Request([
            'widget' => 'heatmap',
            'date_field' => 'created_at',
            'row_field' => 'product',
            'col_field' => 'channel',
            'limit' => 5,
            'from' => '2024-01-01',
            'to' => '2024-01-04',
        ]));

        $this->assertSame('heatmap', $result['type']);
        $this->assertSame('count', $result['metric']);
        $this->assertSame('A', $result['rows'][0]['key']);
        $this->assertEquals(3, $result['rows'][0]['count']);
    }

    /**
     * @test
     */
    public function result_runs_a_stats_widget(): void
    {
        $index = $this->createSalesIndex();

        $result = (new SigmieAnalyticsTool($index))->result(new Request([
            'widget' => 'stats',
            'date_field' => 'created_at',
            'field' => 'amount',
            'from' => '2024-01-01',
            'to' => '2024-01-04',
        ]));

        $this->assertSame('stats', $result['type']);
        $this->assertEquals(5, $result['count']);
        $this->assertEquals(450.0, $result['sum']);
        $this->assertEquals(200.0, $result['max']);
    }

    /**
     * @test
     */
    public function result_runs_a_table_widget(): void
    {
        $index = $this->createSalesIndex();

        $result = (new SigmieAnalyticsTool($index))->result(new Request([
            'widget' => 'table',
            'date_field' => 'created_at',
            'fields' => 'amount,product',
            'sort' => 'amount:desc',
            'limit' => 2,
            'from' => '2024-01-01',
            'to' => '2024-01-04',
        ]));

        $this->assertSame('table', $result['type']);
        $this->assertCount(2, $result['rows']);
        $this->assertEquals(200, $result['rows'][0]['document']['amount']);
    }

    /**
     * @test
     */
    public function result_runs_a_table_widget_with_full_documents_when_fields_are_omitted(): void
    {
        $index = $this->createSalesIndex();

        $result = (new SigmieAnalyticsTool($index))->result(new Request([
            'widget' => 'table',
            'date_field' => 'created_at',
            'limit' => 1,
            'from' => '2024-01-01',
            'to' => '2024-01-04',
        ]));

        $this->assertSame('table', $result['type']);
        $this->assertCount(1, $result['rows']);
        $this->assertArrayHasKey('amount', $result['rows'][0]['document']);
        $this->assertArrayHasKey('channel', $result['rows'][0]['document']);
    }

    /**
     * @test
     */
    public function result_can_return_a_widget_and_document_hits(): void
    {
        $index = $this->createSalesIndex();

        $result = (new SigmieAnalyticsTool($index))->result(new Request([
            'widget' => 'kpi',
            'date_field' => 'created_at',
            'metric' => 'sum',
            'field' => 'amount',
            'from' => '2024-01-01',
            'to' => '2024-01-04',
            'include_hits' => 1,
            'hit_fields' => 'amount,product',
            'hit_sort' => 'amount:desc',
            'hit_filters' => "product:'A'",
            'hit_limit' => 2,
        ]));

        $this->assertEquals(450.0, $result['result']['value']);
        $this->assertSame(3, $result['hits']['total']['value']);
        $this->assertCount(2, $result['hits']['hits']);
        $this->assertSame(200, $result['hits']['hits'][0]['_source']['amount']);
        $this->assertSame('A', $result['hits']['hits'][0]['_source']['product']);
    }

    /**
     * @test
     */
    public function result_runs_a_funnel_widget(): void
    {
        $index = $this->createSalesIndex();

        $result = (new SigmieAnalyticsTool($index))->result(new Request([
            'widget' => 'funnel',
            'date_field' => 'created_at',
            'steps' => '[{"label":"all","filter":"amount>0"},{"label":"big","filter":"amount>=100"}]',
            'from' => '2024-01-01',
            'to' => '2024-01-04',
        ]));

        $this->assertSame('funnel', $result['type']);
        $this->assertSame(['all', 'big'], array_column($result['steps'], 'label'));
        $this->assertEquals(5, $result['steps'][0]['count']);
        $this->assertEquals(2, $result['steps'][1]['count']);
    }

    /**
     * @test
     */
    public function base_filters_scope_the_query(): void
    {
        $index = $this->createSalesIndex();

        $result = (new SigmieAnalyticsTool($index, baseFilters: "product:'A'"))->result(new Request([
            'widget' => 'kpi',
            'date_field' => 'created_at',
            'metric' => 'sum',
            'field' => 'amount',
            'from' => '2024-01-01',
            'to' => '2024-01-04',
        ]));

        $this->assertEquals(370.0, $result['value']);
    }

    /**
     * @test
     */
    public function unknown_date_field_returns_a_correctable_error(): void
    {
        $index = $this->createSalesIndex();

        $json = (new SigmieAnalyticsTool($index))->handle(new Request([
            'widget' => 'trend',
            'date_field' => 'not_a_field',
            'metric' => 'sum',
            'field' => 'amount',
        ]));

        $decoded = json_decode($json, true);

        $this->assertArrayHasKey('error', $decoded);
        $this->assertStringContainsString('not_a_field', $decoded['error']);
    }

    /**
     * @test
     */
    public function result_accepts_a_named_range_and_timezone_offset(): void
    {
        $today = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');

        $index = $this->createSalesIndex();
        $index->merge([new Document(['created_at' => $today, 'amount' => 25, 'product' => 'C'])], refresh: true);

        $result = (new SigmieAnalyticsTool($index))->result(new Request([
            'widget' => 'kpi',
            'date_field' => 'created_at',
            'metric' => 'count',
            'range' => 'this_month',
            'timezone_offset' => 0,
        ]));

        // Only the just-inserted current-month doc; the 2024 seed docs are out of range.
        $this->assertEquals(1, $result['value']);
    }

    /**
     * @test
     */
    public function unknown_range_returns_a_correctable_error(): void
    {
        $index = $this->createSalesIndex();

        $json = (new SigmieAnalyticsTool($index))->handle(new Request([
            'widget' => 'kpi',
            'date_field' => 'created_at',
            'metric' => 'count',
            'range' => 'since_forever',
        ]));

        $decoded = json_decode($json, true);

        $this->assertArrayHasKey('error', $decoded);
        $this->assertStringContainsString('since_forever', $decoded['error']);
    }

    /**
     * @test
     *
     * @dataProvider invalidAnalyticsWidgetArguments
     */
    public function invalid_widget_arguments_return_a_correctable_error(array $arguments, string $expectedError): void
    {
        $index = $this->createSalesIndex();

        $json = (new SigmieAnalyticsTool($index))->handle(new Request([
            'date_field' => 'created_at',
            'from' => '2024-01-01',
            'to' => '2024-01-04',
            ...$arguments,
        ]));

        $decoded = json_decode($json, true);

        $this->assertArrayHasKey('error', $decoded);
        $this->assertStringContainsString($expectedError, $decoded['error']);
    }

    public static function invalidAnalyticsWidgetArguments(): array
    {
        return [
            'funnel missing steps' => [
                ['widget' => 'funnel'],
                'requires steps',
            ],
            'funnel malformed steps' => [
                ['widget' => 'funnel', 'steps' => 'not-json'],
                'funnel steps must be a JSON array',
            ],
            'funnel step missing filter' => [
                ['widget' => 'funnel', 'steps' => '[{"label":"all"}]'],
                'Each funnel step needs',
            ],
            'breakdown malformed aliases' => [
                [
                    'widget' => 'breakdown',
                    'group_by' => 'product',
                    'metric' => 'sum',
                    'field' => 'amount',
                    'bucket_aliases' => 'not-json',
                ],
                'bucket_aliases must be a JSON array',
            ],
            'breakdown non-object alias' => [
                [
                    'widget' => 'breakdown',
                    'group_by' => 'product',
                    'metric' => 'sum',
                    'field' => 'amount',
                    'bucket_aliases' => '["Combined"]',
                ],
                'Each bucket_aliases item must be an object',
            ],
            'breakdown alias missing values' => [
                [
                    'widget' => 'breakdown',
                    'group_by' => 'product',
                    'metric' => 'sum',
                    'field' => 'amount',
                    'bucket_aliases' => '[{"label":"Combined"}]',
                ],
                'needs a non-empty label and values array',
            ],
            'grouped metrics missing metrics' => [
                ['widget' => 'grouped_metrics', 'group_by' => 'product'],
                'requires metrics',
            ],
            'grouped metrics malformed metrics' => [
                ['widget' => 'grouped_metrics', 'group_by' => 'product', 'metrics' => 'not-json'],
                'grouped_metrics metrics must be a JSON array',
            ],
            'grouped metrics non-object metric' => [
                ['widget' => 'grouped_metrics', 'group_by' => 'product', 'metrics' => '["count"]'],
                'Each grouped_metrics metric must be an object',
            ],
            'grouped metrics invalid metric' => [
                ['widget' => 'grouped_metrics', 'group_by' => 'product', 'metrics' => '[{"key":"","metric":"count"}]'],
                'needs a key and valid metric',
            ],
            'grouped metrics missing field' => [
                ['widget' => 'grouped_metrics', 'group_by' => 'product', 'metrics' => '[{"key":"avg_amount","metric":"avg"}]'],
                'non-count grouped_metrics metric needs a field',
            ],
        ];
    }
}
