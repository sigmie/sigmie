<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Sigmie\Base\ElasticsearchException;
use Sigmie\Testing\Assertions\Document;

trait Assertions
{
    use Document;

    public function assertIndex(string $index, callable $callable)
    {
        try {
            $json = $this->indexAPICall($index, 'GET')->json();
        } catch (ElasticsearchException $elasticsearchException) {
            if ($elasticsearchException->getCode() !== 404) {
                throw $elasticsearchException;
            }

            $this->fail(sprintf("Index '%s' does not exist.", $index));
        }

        $this->assertCount(1, $json, sprintf("Expected one index matching '%s'.", $index));
        $indexName = array_key_first($json);

        $indexData = $json[$indexName];

        $assert = new Assert((string) $indexName, $indexData);
        $assert->setElasticsearchConnection($this->getElasticsearchConnection());

        return $callable($assert);
    }

    public function assertIndexExists(string $index): void
    {
        $this->assertEquals(200, $this->indexExistenceStatus($index), sprintf('Failed to assert that index %s exists.', $index));
    }

    public function assertIndexNotExists(string $index): void
    {
        $this->assertEquals(404, $this->indexExistenceStatus($index), sprintf('Failed to assert that index %s not exists.', $index));
    }

    private function indexExistenceStatus(string $index): int
    {
        $separator = str_contains($index, '?') ? '&' : '?';

        return $this->indexAPICall($index.$separator.'allow_no_indices=false', 'HEAD')->code();
    }
}
