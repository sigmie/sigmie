<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis;

use Sigmie\Index\Analysis\TokenFilter\Generic;
use Sigmie\Index\Contracts\CharFilter;

/** A pipeline stage whose definition is supplied by the index or Elasticsearch. */
final class FilterReference extends Generic implements CharFilter
{
    public function __construct(string $name)
    {
        parent::__construct($name, ['type' => $name]);
    }
}
