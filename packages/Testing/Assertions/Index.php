<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;

use Sigmie\Base\APIs\Calls\Index as IndexAPICall;
use Sigmie\Base\Exceptions\ElasticsearchException;

trait Index
{
    use IndexAPICall;
    use Contracts;

    public function assertIndexExists(string $name)
    {
        try {
            $res = $this->indexAPICall("/{$name}", 'HEAD');
            $code = $res->code();
        } catch (ElasticsearchException $e) {
            $code = $e->getCode();
        }

        $this->assertEquals(200, $code, "Failed to assert that index {$name} exists.");
    }

    protected function assertIndexHasMappings(string $index)
    {
        $data = $this->indexData($index);

        $this->assertArrayHasKey('mappings', $data);
    }

    public function assertIndexNotExists(string $name)
    {
        try {
            $res = $this->indexAPICall("/{$name}", 'HEAD');
            $code = $res->code();
        } catch (ElasticsearchException $e) {
            $code = $e->getCode();
        }

        $this->assertEquals(404, $code, "Failed to assert that index {$name} doesn't exists.");
    }

    protected function assertAnalyzerExists(string $index, string $analyzer)
    {
        $data = $this->indexData($index);

        $this->assertArrayHasKey($analyzer, $data['settings']['index']['analysis']['analyzer']);
    }
}
