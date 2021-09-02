<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;


use function Sigmie\Helpers\name_configs;

class Synonyms extends TokenFilter
{
    public function __construct(
        protected string $name,
        protected array $synonyms = [],
        protected bool $expand = false
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

        $instance = new static($name, $settings);

        return $instance;
    }

    protected function getValues(): array
    {
        $res = [];

        foreach ($this->settings as $values) {
            [$first, $value] = $values;
            if (is_array($value)) {
                $from = implode(', ', $value);
                $res[] = "{$from} => {$first}";
            } else {
                $res[] = implode(', ', $values);
            }
        }

        return [
            'synonyms' => $res,
            'expand' => $this->expand
        ];
    }
}
