<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\Type;

class Date extends Type
{
    protected array $formats = [];

    public function format(string $format): void
    {
        $this->formats[] = $format;
    }

    public function toRaw(): array
    {
        return [$this->name => [
            'type' => 'date',
            'format' => implode('|', $this->formats),
        ]];
    }
}
