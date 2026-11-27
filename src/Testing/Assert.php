<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Sigmie\Testing\Assertions\Assertions;

class Assert
{
    use Assertions;

    private array $rawData;

    public function __construct(private string $name, private array $data)
    {
        $this->rawData = $data;
        if (isset($data['settings']) && ! isset($data['settings']['index'])) {
            $settings = dot([]);
            $settings->set($data['settings']);
            $this->data['settings'] = $settings->all();
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function data(): array
    {
        return $this->rawData;
    }
}
