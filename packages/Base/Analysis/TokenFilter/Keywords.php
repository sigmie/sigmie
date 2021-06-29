<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;

use function Sigmie\Helpers\name_configs;

class Keywords extends TokenFilter
{
    public function type(): string
    {
        return 'keyword_marker';
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $configs] = name_configs($raw);

        $instance = new static($name, $configs['keywords']);

        return $instance;
    }

    protected function getValues(): array
    {
        return [
            'keywords' => $this->settings,
        ];
    }
}
