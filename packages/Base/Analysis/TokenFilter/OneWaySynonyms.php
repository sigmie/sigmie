<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;

class OneWaySynonyms extends TokenFilter
{
    public function type(): string
    {
        return 'synonym';
    }

    public static function fromRaw(array $raw)
    {
        $settings = [];

        foreach ($raw['synonyms'] as $value) {
            [$to, $from] = explode('=>', $value);
            $to = explode(', ', $to);
            $from = trim($from);
            $to = array_map(fn ($value) => trim($value), $to);

            $settings[$from] = $to;
        }

        $instance = new static('', $settings);

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
