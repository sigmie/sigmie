<?php

namespace Ni\Elastic\Contract;

interface Action
{
    // TODO
    // public function before();

    // TODO
    // public function after();

    public function result(array $response);
}
