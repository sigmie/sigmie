<?php

declare(strict_types=1);


namespace Sigma\Contract;

interface BootableResponse extends Response, Bootable
{
    public function prepare(array $raw);
}
