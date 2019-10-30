<?php

namespace Sigma\Event;

class Registry
{
    public static function subscribers(): array
    {
        return [
            Mapping::class
        ];
    }
}
