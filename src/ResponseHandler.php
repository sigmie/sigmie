<?php

namespace Ni\Elastic;

use Ni\Elastic\Contract\Response;
use Ni\Elastic\Contract\ResponseHandler as ResponseHandlerInterface;

class ResponseHandler implements ResponseHandlerInterface
{
    /**
     * Raw response handler method
     *
     * @param array $content
     * @param Response $response
     * @return void
     */
    public function handle(array $content, Response $response)
    {
        return $response->result($content);
    }
}
