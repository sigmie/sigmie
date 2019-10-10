<?php

namespace Ni\Elastic\Contract;

interface Action
{
    public function prepare($data): array;
}
