<?php

declare(strict_types=1);

namespace Sigmie\Search\Indices;

class ListedIndex extends Index
{
    public function __construct(array $data)
    {
        $this->name = $data['index'];
    }
}
