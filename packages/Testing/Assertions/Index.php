<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;

use Sigmie\Base\APIs\Index as IndexAPICall;
use Sigmie\Base\Exceptions\ElasticsearchException;

trait Index
{
    use IndexAPICall;
    use Contracts;

    public function assertIndexExists(string $name): void
    {
        try {
            $res = $this->indexAPICall("/{$name}", 'HEAD');
            $code = $res->code();
        } catch (ElasticsearchException $e) {
            $code = $e->getCode();
        }

        $this->assertEquals(200, $code, "Failed to assert that index {$name} exists.");
    }

    public function assertIndexNotExists(string $name): void
    {
        try {
            $res = $this->indexAPICall("/{$name}", 'HEAD');
            $code = $res->code();
        } catch (ElasticsearchException $e) {
            $code = $e->getCode();
        }

        $this->assertEquals(404, $code, "Failed to assert that index {$name} not exists.");
    }

    protected function assertIndexHasMappings(string $index): void
    {
        $data = $this->indexData($index);

        $this->assertArrayHasKey('mappings', $data, "Failed to assert that index {$index} has mappings.");
    }

    protected function assertAnalyzerExists(string $index, string $analyzer): void
    {
        $data = $this->indexData($index);

        $this->assertArrayHasKey(
            $analyzer,
            $data['settings']['index']['analysis']['analyzer'],
            "Failed to assert that the {$analyzer} exists in index {$index}."
        );
    }

    protected function assertAnalyzerNotExists(string $index, string $analyzer): void
    {
        $data = $this->indexData($index);

        $this->assertArrayNotHasKey(
            $analyzer,
            $data['settings']['index']['analysis']['analyzer'],
            "Failed to assert that the {$analyzer} not exists in index {$index}."
        );
    }
}
