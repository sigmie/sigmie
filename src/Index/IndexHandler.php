<?php

namespace Ni\Elastic\Index;

use Ni\Elastic\Action\Action;
use Ni\Elastic\Response\ErrorHandler;
use Ni\Elastic\Response\ResponseHandler;

class IndexHandler implements ResponseHandler
{
    public function handle(array $response, Action $strategy)
    {
        if (isset($response['error'])) {
            return (new ErrorHandler())->handle($response);
        }

        return $strategy->response($response);
    }
}
