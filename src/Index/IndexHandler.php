<?php

namespace Ni\Elastic\Index;

use Ni\Elastic\Contract\Handler;

class IndexHandler implements Handler
{
    public function handle($content, $response)
    {
        // TODO add event triggering after
        return $response->result($content);
    }
}
