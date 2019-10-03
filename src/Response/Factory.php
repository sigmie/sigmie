<?php

namespace Ni\Elastic\Response;

interface Factory
{
    public function create(array $result): Response;
}
