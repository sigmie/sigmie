<?php

declare(strict_types=1);


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
        $index = new Index($response['index']);

        $boot($index);

        return $index;
    }
}
