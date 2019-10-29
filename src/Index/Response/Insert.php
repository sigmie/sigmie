<?php

namespace Sigma\Index\Response;

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
    public function result(array $response)
    {
        dump($response);
        die();
        return new Index($response['index']);
    }
}
