<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Builders;

use Sigmie\Base\Search\BaseSearchBuilder;
use Sigmie\Base\Search\Queries\Term\Term as TermQuery;

class Term extends Search
{
    public function __construct($field, $value)
    {
        $this->query = new TermQuery($field, $value);
    }
}
