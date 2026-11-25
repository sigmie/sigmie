<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use GuzzleHttp\Promise\Promise;
use InvalidArgumentException;
use LengthException;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\Classification\NewClassification;
use Sigmie\Testing\TestCase;
use UnexpectedValueException;

class ClassificationTest extends TestCase
{
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

    /**
     * @test
     */
    public function centroid_based_classification(): void
    {
        $embeddingsApi = $this->embeddingApi;

        $examples = [
            [
                'text' => 'Certified Public Accountant with 5 years experience in financial reporting, tax preparation, and audit. Expert in GAAP, financial analysis, and QuickBooks.',
                'label' => 'accountant',
            ],
            [
                'text' => 'Senior Accountant specializing in general ledger management, reconciliations, and month-end close. Proficient in SAP and financial statement preparation.',
                'label' => 'accountant',
            ],
            [
                'text' => 'Account Executive specializing in enterprise sales, contract negotiations, and client retention. 5 years in SaaS industry.',
                'label' => 'sales',
            ],
            ['text' => 'Sales Manager leading team of 10 reps, developing sales strategies, and achieving revenue targets. Strong in pipeline management.', 'label' => 'sales'],
            ['text' => 'Business Development Representative focused on lead generation, cold calling, and qualifying prospects. Excellent communication skills.', 'label' => 'sales'],
            ['text' => 'Inside Sales Specialist handling inbound/outbound calls, product demonstrations, and closing deals. Proficient in Salesforce and HubSpot.', 'label' => 'sales'],
            ['text' => 'Tax Accountant with expertise in corporate tax compliance, deductions, and IRS regulations. CPA certified with strong analytical skills.', 'label' => 'accountant'],
            ['text' => 'Financial Accountant experienced in budgeting, forecasting, and variance analysis. Advanced Excel and financial modeling capabilities.', 'label' => 'accountant'],
            ['text' => 'Accounting Manager overseeing accounts payable/receivable, payroll processing, and financial reporting. 7 years in manufacturing industry.', 'label' => 'accountant'],
            ['text' => 'Corporate Trainer specializing in leadership development, onboarding programs, and skills training. Certified in instructional design and adult learning.', 'label' => 'trainer'],
            ['text' => 'Fitness Trainer with 6 years experience in personal training, group classes, and nutrition coaching. CPR certified and motivational speaker.', 'label' => 'trainer'],
            ['text' => 'Technical Trainer delivering software training, creating documentation, and conducting workshops. Expert in LMS platforms and e-learning development.', 'label' => 'trainer'],
            ['text' => 'Sales Trainer developing training modules, coaching sales teams, and improving conversion rates. Strong presentation and mentoring skills.', 'label' => 'trainer'],
            ['text' => 'HR Training Specialist designing employee development programs, compliance training, and performance management workshops.', 'label' => 'trainer'],
            ['text' => 'Sales Representative with track record of exceeding quotas by 30%. Expert in B2B sales, relationship building, and CRM software.', 'label' => 'sales'],
        ];

        // Test 1: Accountant
        $result = $this->sigmie->newClassification($embeddingsApi)
            ->labels(['accountant', 'trainer', 'sales'])
            ->examples($examples)
            ->input('Junior Accountant with 2 years experience in bookkeeping, accounts reconciliation, and preparing financial statements. Familiar with Xero accounting software.')
            ->classify();

        $this->assertEquals('accountant', $result->label());
        $this->assertGreaterThan(0.7, $result->confidence());

        // Test 2: Trainer
        $result = $this->sigmie->newClassification($embeddingsApi)
            ->labels(['accountant', 'trainer', 'sales'])
            ->examples($examples)
            ->input('Personal Trainer and wellness coach with certifications in strength training, weight loss programs, and nutritional counseling.')
            ->classify();

        $this->assertEquals('trainer', $result->label());
        $this->assertGreaterThan(0.7, $result->confidence());

        // Test 3: Sales
        $result = $this->sigmie->newClassification($embeddingsApi)
            ->labels(['accountant', 'trainer', 'sales'])
            ->examples($examples)
            ->input('Outside Sales professional with proven success in territory management, client acquisition, and exceeding sales targets in pharmaceutical industry.')
            ->classify();

        $this->assertEquals('sales', $result->label());
        $this->assertGreaterThan(0.7, $result->confidence());
    }

    /**
     * @test
     */
    public function replaced_examples_drop_the_previous_label(): void
    {
        $api = $this->fixedEmbeddings([
            'cat one' => [1.0, 0.0],
            'dog one' => [0.0, 1.0],
            'only dog' => [0.0, 1.0],
            'query cat' => [1.0, 0.0],
        ]);
        $classification = new NewClassification($api);
        $classification->examples([
            ['text' => 'cat one', 'label' => 'cat'],
            ['text' => 'dog one', 'label' => 'dog'],
        ])->input('query cat')->classify();

        $second = $classification->examples([
            ['text' => 'only dog', 'label' => 'dog'],
        ])->input('query cat')->classify();

        $this->assertSame('dog', $second->label());
        $this->assertArrayNotHasKey('cat', $second->allScores());
    }

    /**
     * @test
     */
    public function a_numeric_label_stays_a_string(): void
    {
        $api = $this->fixedEmbeddings([
            'a' => [1.0, 0.0],
            'q' => [1.0, 0.0],
        ]);

        $result = (new NewClassification($api))
            ->examples([
                ['text' => 'a', 'label' => '10'],
            ])
            ->input('q')
            ->classify();

        $this->assertSame('10', $result->label());
        $this->assertSame(1.0, $result->score('10'));
    }

    public function test_repeated_maximum_embeddings_have_finite_confidence(): void
    {
        $api = $this->fixedEmbeddings(['cat' => [PHP_FLOAT_MAX, 0.0]]);
        $examples = array_fill(0, 3, ['text' => 'cat', 'label' => 'cat']);
        $result = (new NewClassification($api))->examples($examples)->input('cat')->classify();

        $this->assertSame(1.0, $result->confidence());
    }

    public function test_each_batch_contributes_to_the_label_centroid(): void
    {
        $api = $this->fixedEmbeddings(['a' => [1, 0], 'b' => [0, 16], 'query' => [1, 1]]);
        $examples = array_fill(0, 16, ['text' => 'a', 'label' => 'cat']);
        $examples[] = ['text' => 'b', 'label' => 'cat'];

        $result = (new NewClassification($api))->examples($examples)->input('query')->classify();

        $this->assertEqualsWithDelta(1.0, $result->confidence(), 1e-12);
    }

    public function test_only_allowed_labels_are_scored(): void
    {
        $api = $this->fixedEmbeddings(['cat' => [1, 0], 'dog' => [0, 1]]);
        $result = (new NewClassification($api))->labels(['dog'])->examples([
            ['text' => 'cat', 'label' => 'cat'],
            ['text' => 'dog', 'label' => 'dog'],
        ])->input('cat')->classify();

        $this->assertSame('dog', $result->label());
        $this->assertSame(['dog' => 0.0], $result->allScores());
    }

    public function test_missing_input_is_rejected_before_embedding_examples(): void
    {
        $api = $this->createMock(EmbeddingsApi::class);
        $api->method('maxBatchSize')->willReturn(16);
        $api->expects($this->never())->method('batchEmbed');
        $api->expects($this->never())->method('embed');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Classification requires input.');

        (new NewClassification($api))->examples([['text' => 'cat', 'label' => 'cat']])->classify();
    }

    public function test_an_incomplete_example_batch_does_not_build_a_centroid(): void
    {
        $api = $this->createMock(EmbeddingsApi::class);
        $api->method('maxBatchSize')->willReturn(16);
        $api->method('batchEmbed')->willReturn([['vector' => [1.0, 0.0]]]);
        $api->expects($this->never())->method('embed');
        $this->expectException(UnexpectedValueException::class);

        (new NewClassification($api))->examples([
            ['text' => 'cat', 'label' => 'cat'],
            ['text' => 'kitten', 'label' => 'cat'],
        ])->input('cat')->classify();
    }

    /** @dataProvider ineligibleExamples */
    public function test_no_eligible_examples(array $labels, array $examples): void
    {
        $api = $this->fixedEmbeddings(['cat' => [1, 0]]);
        $this->expectException(InvalidArgumentException::class);

        (new NewClassification($api))->labels($labels)->examples($examples)->input('cat')->classify();
    }

    public static function ineligibleExamples(): array
    {
        return [
            'empty examples' => [['cat'], []],
            'no matching label' => [['dog'], [['text' => 'cat', 'label' => 'cat']]],
            'empty allowlist' => [[], [['text' => 'cat', 'label' => 'cat']]],
        ];
    }

    /** @dataProvider similarityCases */
    public function test_vector_scores(array $query, array $example, float $expected): void
    {
        $api = $this->fixedEmbeddings(['query' => $query, 'example' => $example]);
        $result = (new NewClassification($api))->examples([['text' => 'example', 'label' => 'cat']])
            ->input('query')->classify();

        $this->assertEqualsWithDelta($expected, $result->confidence(), 1e-12);
    }

    public static function similarityCases(): array
    {
        return [
            'large finite vectors' => [[1e200, 1e200], [1e200, 0], sqrt(0.5)],
            'different dimensions' => [[1, 0], [1], 0.0],
        ];
    }

    private function runsWithoutCluster(): bool
    {
        return $this->getName(false) !== 'centroid_based_classification';
    }

    private function fixedEmbeddings(array $vectors): EmbeddingsApi
    {
        return new class($vectors) implements EmbeddingsApi
        {
            public function __construct(private array $vectors) {}

            public function embed(string $text, int $dimensions): array
            {
                return $this->vectors[$text] ?? array_fill(0, $dimensions, 0.0);
            }

            public function batchEmbed(array $payload): array
            {
                if (count($payload) > $this->maxBatchSize()) {
                    throw new LengthException('Embedding batch exceeds provider limit.');
                }

                foreach ($payload as $key => $item) {
                    $text = (string) ($item['text'] ?? '');
                    $dims = (int) ($item['dims'] ?? 0);
                    $payload[$key]['vector'] = $this->vectors[$text] ?? array_fill(0, max(1, $dims), 0.0);
                }

                return $payload;
            }

            public function promiseEmbed(string $text, int $dimensions): Promise
            {
                $promise = new Promise;
                $promise->resolve($this->embed($text, $dimensions));

                return $promise;
            }

            public function model(): string
            {
                return 'fixed';
            }

            public function maxBatchSize(): int
            {
                return 16;
            }
        };
    }
}
