<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\TokenFilter;

abstract class WordListFilter extends TokenFilter
{
    public function __construct(string $name, array|string $settings = [])
    {
        parent::__construct($name, [
            static::WORD_LIST => is_array($settings) ? array_values($settings) : $settings,
        ]);
    }

    public function settings(array $settings): void
    {
        parent::settings([static::WORD_LIST => array_values($settings)]);
    }

    protected function getValues(): array
    {
        return $this->settings;
    }
}
