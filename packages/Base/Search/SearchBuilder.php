<?php

declare(strict_types=1);

namespace Sigmie\Base\Search;

use Sigmie\Base\Contracts\HttpConnection;
use Sigmie\Base\Search\Builders\Boolean;
use Sigmie\Base\Search\Builders\Term;

class SearchBuilder
{
    public function __construct(protected string $index, protected HttpConnection $httpConnection)
    {
    }
    public function term($field, $value)
    {
        $search = new Term($field, $value);

        $search->index($this->index);
        $search->setHttpConnection($this->httpConnection);

        return $search;
    }

    public function bool(callable $callable)
    {
        $search = new Boolean($callable);

        $search->index($this->index);
        $search->setHttpConnection($this->httpConnection);

        return $search;
    }
}
