<?php

namespace Sigmie\Parse\Contracts;

interface Parser
{
    public function parse(string $string);

    public function errors(): array;
}
