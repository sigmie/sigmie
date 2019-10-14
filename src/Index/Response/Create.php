<?php

namespace Sigma\Index\Response;

use Sigma\Contract\Response;

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
