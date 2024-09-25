<?php

declare(strict_types=1);

namespace Sigmie\Search\Autocomplete;

use Sigmie\Shared\Contracts\ToRaw;

abstract class Processor implements ToRaw
{
    abstract protected function type(): string;

    abstract protected function values(): array;

    public function toRaw(): array
    {
        $res = [
            $this->type() => $this->values(),
        ];

        return $res;
    }
}
