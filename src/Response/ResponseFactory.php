<?php

namespace Ni\Elastic\Response;

interface ResponseFactory
{
    public function create(array $result): Response;
}
