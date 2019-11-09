<?php

declare(strict_types=1);


namespace Sigma\Index\Response;

use Closure;
use Sigma\Collection;
use Sigma\Contract\Response;
use Sigma\Index\IndexCollection;

class Listing implements Response
{
    /**
     * Create and return an IndexCollection
     *
     * @param array $response
     *
     * @return IndexCollection
     */
    public function result($response, Closure $boot): Collection
    {
        $response = new IndexCollection($response);

        return $response;
    }
}
