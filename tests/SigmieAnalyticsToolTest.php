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

                return $props;
            }
        };

        $index->create();

        $index->merge([
            new Document(['created_at' => '2024-01-01', 'amount' => 100, 'product' => 'A']),
            new Document(['created_at' => '2024-01-01', 'amount' => 50, 'product' => 'B']),
            new Document(['created_at' => '2024-01-02', 'amount' => 200, 'product' => 'A']),
            new Document(['created_at' => '2024-01-03', 'amount' => 30, 'product' => 'B']),
            new Document(['created_at' => '2024-01-03', 'amount' => 70, 'product' => 'A']),
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
        $this->assertStringContainsString('amount', $description);
        $this->assertStringContainsString('median', $description);
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

        foreach (['metric', 'field', 'interval', 'group_by', 'limit', 'bucket_size', 'percents', 'from', 'to', 'filters'] as $name) {
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
}
