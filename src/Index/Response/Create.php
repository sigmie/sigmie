<?php

namespace Ni\Elastic\Index\Response;

use Ni\Elastic\Contract\Response;

class Create implements Response
{
    /**
     * Return the acknowledged flag
     * as success indicator
     *
     * @param array $response
     *
     * @return boolean
     */
    public function result(array $response)
    {
        return $response['acknowledged'];
    }
}
