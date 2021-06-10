<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;

use function Sigmie\Helpers\name_configs;

class TwoWaySynonyms extends TokenFilter
{
    public function type(): string
    {
        return 'synonym';
    }

    public static function fromRaw(array $raw)
    {
        [$name, $configs] = name_configs($raw);
        $settings = [];

        foreach ($configs['synonyms'] as $value) {
            $value = explode(',', $value);
            $value = array_map(fn ($value) => trim($value), $value);
            $settings[] = $value;
        }

        $instance = new static($name, $settings, $configs['priority']);

        return $instance;
    }

    protected function getValues(): array
    {
        $synonyms = array_map(fn ($value) => implode(', ', $value), $this->settings);

        return [
            'synonyms' => $synonyms,
        ];
    }
}
