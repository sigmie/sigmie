<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\TokenFilter;

use function Sigmie\Functions\name_configs;

class Keywords extends TokenFilter
{
    public function type(): string
    {
        return 'keyword_marker';
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $configs] = name_configs($raw);

        return new static($name, $configs['keywords']);
    }

    protected function getValues(): array
    {
        return [
            'keywords' => $this->settings,
        ];
    }
}
