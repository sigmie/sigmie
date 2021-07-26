<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings\Types;

use Sigmie\Base\Mappings\PropertyType;

class Date extends PropertyType
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
            'format' => implode('|', $this->formats)
        ]];
    }
}
