<?php

namespace Sigma\Index\Response;

use Closure;
use Sigma\Contract\Response;
use Sigma\Exception\ActionFailed;
use Sigma\Index\Index;

class Insert implements Response
{
    /**
     * Return the acknowledged flag
     * as success indicator
     *
     * @param array $response
     *
     * @return boolean
     */
    public function result($response, Closure $boot)
    {
        return new Index($response['index']);
    }
}
