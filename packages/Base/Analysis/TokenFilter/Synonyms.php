<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;


use function Sigmie\Helpers\name_configs;

class Synonyms extends TokenFilter
{
    protected bool $expand = true;

    public function type(): string
    {
        return 'synonym';
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $configs] = name_configs($raw);
        $settings = [];

        foreach ($configs['synonyms'] as $value) {

            if (str_contains($value, '=>')) {
                [$to, $from] = explode('=>', $value);

                $to = explode(', ', $to);
                $from = trim($from);
                $to = array_map(fn ($value) => trim($value), $to);

                $settings[] = [$from => $to];
                continue;
            }

            $value = explode(',', $value);
            $value = array_map(fn ($value) => trim($value), $value);

            $settings[] = $value;
        }

        $instance = new static($name, $settings, $configs['priority']);

        return $instance;
    }

    protected function getValues(): array
    {
        $res = [];
        foreach ($this->settings as $key => $value) {
            if (is_int($key)) {
                $res[] = implode(', ', $value);
            } else {
                $from = implode(', ', $value);
                $res[] = "{$from} => {$key}";
            }
        }

        return [
            'synonyms' => $res,
        ];
    }
}
