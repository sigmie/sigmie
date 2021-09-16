<?php

namespace Sigmie\Testing;

use Sigmie\Base\APIs\Index as IndexAPI;
use Sigmie\Base\Exceptions\ElasticsearchException;
use Sigmie\Testing\Assertions\CharFilter;
use Sigmie\Testing\Assertions\Filter;
use Sigmie\Testing\Assertions\Index;
use Sigmie\Testing\Assertions\Mapping;
use Sigmie\Testing\Assertions\Tokenizer;
use Sigmie\Testing\Assertions\Analyzer;


trait Assertions
{
    public function assertIndex(string $index, callable $callable)
    {
        $json = $this->indexAPICall($index, 'GET')->json();
        $indexName = array_key_first($json);

        $indexData = $json[$indexName];

        return $callable(new Assert($index, $indexData));
    }

    public function assertIndexExists(string $index): void
    {
        try {
            $res = $this->indexAPICall("/{$index}", 'HEAD');
            $code = $res->code();
        } catch (ElasticsearchException $e) {
            $code = $e->getCode();
        }

        $this->assertEquals(200, $code, "Failed to assert that index {$index} exists.");
    }

    public function assertIndexNotExists(string $index): void
    {
        try {
            $res = $this->indexAPICall("/{$index}", 'HEAD');
            $code = $res->code();
        } catch (ElasticsearchException $e) {
            $code = $e->getCode();
        }

        $this->assertEquals(404, $code, "Failed to assert that index {$index} not exists.");
    }
}
