<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Sigmie\Testing\Assertions\Document;

trait Assertions
{
    use Document;

    public function assertIndex(string $index, callable $callable)
    {
        $json = $this->indexAPICall($index, 'GET')->json();
        $indexName = array_key_first($json);

        $indexData = $json[$indexName];

        return $callable(new Assert($index, $indexData));
    }

    public function assertIndexExists(string $index): void
    {
        $res = $this->indexAPICall($index, 'HEAD');

        $this->assertEquals(200, $res->code(), sprintf('Failed to assert that index %s exists.', $index));
    }

    public function assertIndexNotExists(string $index): void
    {
        $res = $this->indexAPICall($index, 'HEAD');

        $this->assertEquals(404, $res->code(), sprintf('Failed to assert that index %s not exists.', $index));
    }
}
