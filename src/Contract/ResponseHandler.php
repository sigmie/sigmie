<?php

namespace Ni\Elastic\Contract;

use Ni\Elastic\Collection;
use Ni\Elastic\Element;

interface ResponseHandler
{
    /**
     * Response handling method Contract
     *
     * @param array $content
     * @param Response $response
     *
     * @return bool|Element|Collection
     */
    public function handle(array $content, Response $response);
}
