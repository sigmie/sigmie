<?php

namespace Ni\Elastic\Contract;

use Ni\Elastic\Action\Action;

interface ResponseHandler
{
    public function handle(array $content, Response $response);
}
