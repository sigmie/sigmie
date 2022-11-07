<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\Types\Type;

class Date extends Type
{
    public function __construct(
        string $name,
        protected array $formats = ['yyyy-MM-dd HH:mm:ss.SSSSSS']
    ) {
        parent::__construct($name);
    }

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
