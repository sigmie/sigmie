<?php

namespace Ni\Elastic\Contract;

interface Action
{
    public function before(): string;

    public function after(): string;

    public function prepare($data): array;
}
