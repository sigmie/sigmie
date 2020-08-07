<?php

declare(strict_types=1);

namespace Sigmie\Contracts;

interface Result
{
    public function populate(array $data);
}
