<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;

class OneWaySynonyms extends TokenFilter
{
    protected function getName(): string
    {
        return  'one_way_synonyms';
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string
    {
        return 'synonym';
    }

    public static function fromRaw(array $raw)
    {
        $instance = new static('', $raw['synonyms']);

        return $instance;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
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
