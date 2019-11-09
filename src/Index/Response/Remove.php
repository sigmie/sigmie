<?php

declare(strict_types=1);


namespace Sigma\Index\Response;

use Closure;
use Sigma\Contract\Response;
use Sigma\Contract\Response\Remove as RemoveResponse;

class Remove implements Response
{
    /**
     * Return the acknowledged flag
     * as success indicator
     *
     * @param array $response
     *
     * @return boolean
     */
    public function result($response, Closure $boot): bool
    {
        return $response['acknowledged'];
    }
}
