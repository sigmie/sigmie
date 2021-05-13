<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis;

class Analyzer
{
    public function __construct(protected string $name)
    {
    }

    public function name()
    {
        return $this->name;
    }
}
