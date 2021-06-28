<?php

declare(strict_types=1);

namespace Sigmie\Testing\Shared;

use Sigmie\Base\APIs\Index;

trait IndexData
{
    use Index;

    protected function indexData(string $name): array
    {
        $json = $this->indexAPICall($name, 'GET')->json();
        $indexName = array_key_first($json);

        return $json[$indexName];
    }
}
