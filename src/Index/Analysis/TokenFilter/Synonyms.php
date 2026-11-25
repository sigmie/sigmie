<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\TokenFilter;

use function Sigmie\Functions\name_configs;

class Synonyms extends TokenFilter
{
    public function __construct(
        string $name,
        protected array $synonyms = [],
        protected bool $expand = true
    ) {
        parent::__construct(
            $name,
            $this->synonyms
        );
    }

    public function type(): string
    {
        return 'synonym';
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $configs] = name_configs($raw);
        $configs['expand'] = filter_var($configs['expand'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $filter = new static($name);
        $filter->settings($configs + ['type' => $filter->type()]);

        return $filter;
    }

    protected function getValues(): array
    {
        if (($this->settings['type'] ?? null) === $this->type()) {
            return $this->settings;
        }

        $res = [];
        $escape = fn (string $term): string => strtr($term, ['\\' => '\\\\', ',' => '\\,', '=' => '\\=', '#' => '\\#']);

        foreach ($this->settings as $values) {
            if (is_string($values)) {
                $res[] = $values;

                continue;
            }

            $values = array_values($values);
            if (is_array($values[1] ?? null)) {
                [$first, $value] = $values;
                $from = implode(', ', array_map($escape, $value));
                $res[] = sprintf('%s => %s', $from, $escape($first));
            } else {
                $res[] = implode(', ', array_map($escape, $values));
            }
        }

        return [
            'synonyms' => $res,
            'expand' => $this->expand,
        ];
    }
}
