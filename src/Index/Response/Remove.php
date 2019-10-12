<?php

namespace Ni\Elastic\Index\Response;

use Ni\Elastic\Contract\Response;
use Ni\Elastic\Contract\Response\Remove as RemoveResponse;

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
    public function result(array $response): bool
    {
        return $response['acknowledged'];
    }
}
