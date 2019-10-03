<?php

namespace Ni\Elastic\Response;

use Ni\Elastic\Response\Factory;
use Ni\Elastic\Response\Index\IndexResponseFactory;
use Ni\Elastic\Response\Response;

class ResponseFactory implements Factory
{
    public function create(array $result): Response
    {
        return (new IndexResponseFactory())->create($result);
    }
}
