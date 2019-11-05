<?php

namespace Sigma\Index\Response;

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
    public function result($response): bool
    {
        return $response['acknowledged'];
    }
}
