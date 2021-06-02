<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;

class Stemmer extends TokenFilter
{
    protected function getName(): string
    {
        return  'stemmer_overrides';
    }

    public function name(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function type(): string
    {
        return 'stemmer_override';
    }

    public static function fromRaw(array $raw)
    {
        $instance = new static('', $raw['rules']);

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
            'rules' => $rules,
        ];
    }
}
