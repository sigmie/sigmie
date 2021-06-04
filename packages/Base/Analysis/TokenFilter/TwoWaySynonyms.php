<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;

class TwoWaySynonyms extends TokenFilter
{
    protected function getName(): string
    {
        return  'two_way_synonyms';
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
        $settings = [];

        foreach ($raw['synonyms'] as $value) {
            $value = explode(',', $value);
            $value = array_map(fn ($value) => trim($value), $value);
            $settings[] = $value;
        }

        $instance = new static('', $settings);

        return $instance;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    protected function getValues(): array
    {
        $synonyms = array_map(fn ($value) => implode(', ', $value), $this->settings);

        return [
            'synonyms' => $synonyms,
        ];
    }
}
