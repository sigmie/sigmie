<?php

namespace Ni\Elastic\Response;

interface FailureResponse extends Response
{
    public function error(): bool;

    public function index(): string;
}
