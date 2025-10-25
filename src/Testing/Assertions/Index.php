<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;

use Sigmie\Base\APIs\Index as IndexAPICall;

trait Index
{
    use Contracts;
    use IndexAPICall;

    private string $name;

    private array $data;

    public function assertIndexHasPipeline(string $pipeline): void
    {
        $this->assertArrayHasKey('default_pipeline', $this->data['settings']['index'], sprintf('Failed to assert that index %s has a default pipeline.', $this->name));
        $this->assertEquals($pipeline, $this->data['settings']['index']['default_pipeline'], sprintf('Failed to assert that index %s has %s pipeline.', $this->name, $pipeline));
    }

    public function assertIndexHasNotPipeline(): void
    {
        $this->assertArrayNotHasKey('default_pipeline', $this->data['settings']['index'], sprintf('Failed to assert that index %s has a default pipeline.', $this->name));
    }

    public function assertIndexHasMappings(): void
    {
        $this->assertArrayHasKey('mappings', $this->data, sprintf('Failed to assert that index %s has mappings.', $this->name));
    }

    public function assertAnalyzerExists(string $analyzer): void
    {
        $this->assertArrayHasKey(
            $analyzer,
            $this->data['settings']['index']['analysis']['analyzer'],
            sprintf('Failed to assert that the %s exists in index %s.', $analyzer, $this->name)
        );
    }

    public function assertNormalizerExists(string $normalizer): void
    {
        $this->assertArrayHasKey(
            $normalizer,
            $this->data['settings']['index']['analysis']['normalizer'],
            sprintf('Failed to assert that the %s normalizer exists in index %s.', $normalizer, $this->name)
        );
    }

    public function assertAnalyzerNotExists(string $analyzer): void
    {
        $this->assertArrayNotHasKey(
            $analyzer,
            $this->data['settings']['index']['analysis']['analyzer'],
            sprintf('Failed to assert that the %s not exists in index %s.', $analyzer, $this->name)
        );
    }
}
