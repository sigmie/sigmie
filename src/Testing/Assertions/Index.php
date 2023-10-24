<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;

use Sigmie\Base\APIs\Index as IndexAPICall;

trait Index
{
    use IndexAPICall;
    use Contracts;

    private string $name;

    private array $data;

    public function assertIndexHasMappings(): void
    {
        $this->assertArrayHasKey('mappings', $this->data, "Failed to assert that index {$this->name} has mappings.");
    }

    public function assertIndexHasPipeline(string $pipeline): void
    {
        $this->assertArrayHasKey('default_pipeline', $this->data['settings']['index'], "Failed to assert that index {$this->name} has a default pipeline.");
        $this->assertEquals($pipeline, $this->data['settings']['index']['default_pipeline'], "Failed to assert that index {$this->name} has {$pipeline} pipeline.");
    }

    public function assertIndexHasNotPipeline(): void
    {
        $this->assertArrayNotHasKey('default_pipeline', $this->data['settings']['index'], "Failed to assert that index {$this->name} has a default pipeline.");
    }

    public function assertAnalyzerExists(string $analyzer): void
    {
        $this->assertArrayHasKey(
            $analyzer,
            $this->data['settings']['index']['analysis']['analyzer'],
            "Failed to assert that the {$analyzer} exists in index {$this->name}."
        );
    }

    public function assertNormalizerExists(string $normalizer): void
    {
        $this->assertArrayHasKey(
            $normalizer,
            $this->data['settings']['index']['analysis']['normalizer'],
            "Failed to assert that the {$normalizer} normalizer exists in index {$this->name}."
        );
    }

    public function assertAnalyzerNotExists(string $analyzer): void
    {
        $this->assertArrayNotHasKey(
            $analyzer,
            $this->data['settings']['index']['analysis']['analyzer'],
            "Failed to assert that the {$analyzer} not exists in index {$this->name}."
        );
    }
}
