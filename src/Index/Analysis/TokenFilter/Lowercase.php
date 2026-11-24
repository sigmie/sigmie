<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\TokenFilter;

use function Sigmie\Functions\name_configs;

class Lowercase extends Generic
{
    public function type(): string
    {
        return 'lowercase';
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $settings] = name_configs($raw);
        $filter = new static($name);
        $filter->settings($settings);

        return $filter;
    }
}
