<?php

namespace Ni\Elastic\Index\Response;

use Ni\Elastic\Contract\Response\Create as CreateResponse;
use Ni\Elastic\Contract\Response;
use Ni\Elastic\Contract\Subscribable;
use Ni\Elastic\Index\Index;

class Create implements Response
{
    public function result($response)
    {
        return $response['acknowledged'];
    }
}
