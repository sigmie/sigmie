<?php

namespace Ni\Elastic\Response;

use Ni\Elastic\Action\Action;

interface ResponseHandler
{
    public function handle(array $response, Action $strategy);
}
