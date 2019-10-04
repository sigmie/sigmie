<?php

namespace Ni\Elastic;

use Ni\Elastic\Response\Response;

interface Manageable
{
    public function create(array $values): Response;

    public function remove(string $identifier): Response;

    public function list(array $params): Response;

    public function get(array $params): Response;
}
