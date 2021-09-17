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

    public function assertAnalyzerExists(string $analyzer): void
    {
        $this->assertArrayHasKey(
            $analyzer,
            $this->data['settings']['index']['analysis']['analyzer'],
            "Failed to assert that the {$analyzer} exists in index {$this->name}."
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
