<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use PHPUnit\Framework\Assert as PHPUnit;
use Sigmie\Testing\Assertions\Assertions;

class Assert
{
    use Assertions;

    public function __construct(private string $name, private array $data)
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function data(): array
    {
        return $this->data;
    }
}
