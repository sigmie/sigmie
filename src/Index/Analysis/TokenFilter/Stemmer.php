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
        $filter = new static($name);
        $filter->settings($configs + ['type' => $filter->type()]);

        return $filter;
    }

    protected function getValues(): array
    {
        if (($this->settings['type'] ?? null) === $this->type()) {
            return $this->settings;
        }

        $rules = [];

        foreach ($this->settings as $rule) {
            [$to, $from] = array_values($rule);
            $from = implode(', ', $from);
            $rules[] = sprintf('%s => %s', $from, $to);
        }

        return [
            'rules' => $rules,
        ];
    }
}
