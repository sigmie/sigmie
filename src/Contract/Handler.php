<?php

namespace Ni\Elastic\Contract;

use Ni\Elastic\Action\Action;

interface Handler
{
    public function handle($content, $strategy);
}
