<?php

namespace Ni\Elastic\Index;

use Ni\Elastic\Contract\Response;
use Ni\Elastic\Contract\ResponseHandler as ResponseHandlerInterface;

class ResponseHandler implements ResponseHandlerInterface
{
    public function handle(array $content, Response $response)
    {
        return $response->result($content);
    }
}
