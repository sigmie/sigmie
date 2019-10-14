<?php

namespace Sigma\Contract;

use Sigma\Collection;
use Sigma\Element;

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
