<?php

namespace Ni\Elastic\Index;

use Ni\Elastic\Contract\Handler;
use Ni\Elastic\Contract\Response;

class IndexHandler implements Handler
{
    public function handle($content, $response)
    {
        return $response->result($content);
    }
}
