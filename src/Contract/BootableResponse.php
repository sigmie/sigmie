<?php

namespace Sigma\Contract;

interface BootableResponse extends Response, Bootable
{
    public function prepare(array $raw);
}
