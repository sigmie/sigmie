<?php

declare(strict_types=1);

namespace Sigmie\Base\Search;

use Sigmie\Base\Search\Builders\Boolean;
use Sigmie\Base\Search\Builders\Term;

class SearchBuilder
{
    public function term($field, $value)
    {
        return new Term($field, $value);
    }

    public function bool(callable $callable)
    {
        return new Boolean($callable);
    }
}
