<?php

declare(strict_types=1);

namespace Sigmie\Testing\Shared;

trait IndexData
{
    protected function indexData(string $name): array
    {
        $json = $this->indexAPICall($name, 'GET')->json();
        $indexName = array_key_first($json);

        return $json[$indexName];
    }
}
