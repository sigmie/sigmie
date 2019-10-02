<?php

namespace Ni\Elastic\Response\Index;

interface IndexResponse
{
    public function acknowledged(): bool;

    public function index(): string;
}
