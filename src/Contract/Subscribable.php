<?php

namespace Ni\Elastic\Contract;

interface Subscribable
{
    public function beforeEvent(): string;

    public function afterEvent(): string;
}
