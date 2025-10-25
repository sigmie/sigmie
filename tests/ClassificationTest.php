<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Testing\TestCase;

class ClassificationTest extends TestCase
{
    /**
     * @test
     */
    public function centroid_based_classification(): void
    {
        $embeddingsApi = $this->embeddingApi;

        $examples = [
            [
                'text' => 'Certified Public Accountant with 5 years experience in financial reporting, tax preparation, and audit. Expert in GAAP, financial analysis, and QuickBooks.',
                'label' => 'accountant'
            ],
            [
                'text' => 'Senior Accountant specializing in general ledger management, reconciliations, and month-end close. Proficient in SAP and financial statement preparation.',
                'label' => 'accountant'
            ],
            [
                'text' => 'Account Executive specializing in enterprise sales, contract negotiations, and client retention. 5 years in SaaS industry.',
                'label' => 'sales'
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
    public function kmeans_clustering(): void
    {
        $embeddingsApi = $this->embeddingApi;

        $texts = [
            'The Lion King',
            'Beauty and the Beast',
            'Aladdin',
            'Frozen',
            'Moana',
            'Mulan',
            'The Little Mermaid',
            'Tangled',
            'Zootopia',
            'Toy Story',
        ];

        $result = $this->sigmie->newClustering($embeddingsApi)
            ->texts($texts)
            ->algorithm('kmeans')
            ->clusters(3)
            ->fit();

        $this->assertEquals(3, $result->clusterCount());
        $this->assertCount(10, $result->assignments());

        $clusters = $result->clusters();
        $this->assertCount(3, $clusters);

        // Each cluster should have at least one item
        foreach ($clusters as $items) {
            $this->assertGreaterThan(0, count($items));
        }
    }

    /**
     * @test
     */
    public function hdbscan_clustering(): void
    {
        $embeddingsApi = $this->embeddingApi;

        $texts = [
            'The Lion King',
            'Beauty and the Beast',
            'Aladdin',
            'Frozen',
            'Moana',
            'Mulan',
            'The Little Mermaid',
            'Micheal Jackson'
        ];

        $result = $this->sigmie->newClustering($embeddingsApi)
            ->texts($texts)
            ->algorithm('hdbscan')
            ->fit();

        $this->assertGreaterThanOrEqual(0, $result->clusterCount());
        $this->assertCount(8, $result->assignments());

        $clusters = $result->clusters();

        $this->assertGreaterThan(0, count($clusters));
    }
}
