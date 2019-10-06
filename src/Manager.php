<?php

namespace Ni\Elastic;

use Ni\Elastic\Response\Response;

interface Manager
{
    public function create($element): Element;

    // public function remove(string $identifier): Response;

    // public function list(array $params): Response;

    // public function get(string $identifier): Response;
}
