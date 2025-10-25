<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\TokenFilter;

use function Sigmie\Functions\name_configs;

class Stemmer extends TokenFilter
{
    final public function type(): string
    {
        return 'stemmer_override';
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $configs] = name_configs($raw);
        $settings = [];

        foreach ($configs['rules'] as $value) {
            [$to, $from] = explode('=>', $value);
            $to = explode(', ', $to);
            $from = trim($from);
            $to = array_map(fn ($value): string => trim($value), $to);

            $settings[$from] = $to;
        }

        return new static($name, $settings);
    }

    protected function getValues(): array
    {
        $rules = [];

        foreach ($this->settings as [$to, $from]) {
            $from = implode(', ', $from);
            $rules[] = sprintf('%s => %s', $from, $to);
            // foreach ($from as $word) {
            //     $rules[] = "{$word} => {$to}";
            // }
        }

        return [
            'rules' => $rules,
        ];
    }
}
