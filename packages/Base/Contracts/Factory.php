<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

interface Factory
{
    public function connection(HttpConnection $connection): self;
}
