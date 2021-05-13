<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings\Types;

use Sigmie\Base\Contracts\Type;

class Date extends BaseType
{
    protected array $formats = [];

    public function format(string $format)
    {
        $this->formats[] = $format;
    }

    protected function raw()
    {
        return [
            $this->name => [
                'type' => 'date',
                'format' => implode('|', $this->formats)
            ]
        ];
    }
}
