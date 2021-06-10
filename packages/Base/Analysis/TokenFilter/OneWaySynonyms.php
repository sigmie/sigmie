<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;

use function Sigmie\Helpers\name_configs;

class OneWaySynonyms extends TokenFilter
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
            [$to, $from] = explode('=>', $value);
            $to = explode(', ', $to);
            $from = trim($from);
            $to = array_map(fn ($value) => trim($value), $to);

            $settings[$from] = $to;
        }

        $instance = new static($name, $settings, $configs['priority']);

        return $instance;
    }

    protected function getValues(): array
    {
        $rules = [];
        foreach ($this->settings as $to => $from) {
            $from = implode(', ', $from);
            $rules[] = "{$from} => {$to}";
        }

        return [
            'synonyms' => $rules,
        ];
    }
}
