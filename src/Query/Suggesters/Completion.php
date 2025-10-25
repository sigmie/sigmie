<?php

declare(strict_types=1);

namespace Sigmie\Query\Suggesters;

use Sigmie\Query\Suggesters\Enums\SuggesterType;

class Completion extends Suggester
{
    protected string $prefix = '';

    protected string $analyzer;

    protected bool $fuzzy = false;

    protected int $fuzzyMinLength = 3;

    protected int $fuzzyPrefixLenght = 1;

    public function type(): SuggesterType
    {
        return SuggesterType::Completion;
    }

    public function fuzzyMinLegth(int $lenght = 3): static
    {
        $this->fuzzyMinLength = $lenght;

        return $this;
    }

    public function fuzzyPrefixLenght(int $lenght = 1): static
    {
        $this->fuzzyPrefixLenght = $lenght;

        return $this;
    }

    public function analyzer(string $analyzer): static
    {
        $this->analyzer = $analyzer;

        return $this;
    }

    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function fuzzy(bool $fuzzy = true): static
    {
        $this->fuzzy = $fuzzy;

        return $this;
    }

    public function toRaw(): array
    {
        $res = parent::toRaw();

        $res[$this->name][$this->type()->value]['skip_duplicates'] = true;
        $res[$this->name]['prefix'] = $this->prefix;

        if ($this->fuzzy ?? false) {
            $res[$this->name][$this->type()->value]['fuzzy'] = [
                'fuzziness' => 'AUTO',
                'prefix_length' => $this->fuzzyPrefixLenght,
                'min_length' => $this->fuzzyMinLength,
            ];
        }

        if ($this->analyzer ?? false) {
            $res[$this->name][$this->type()->value]['analyzer'] = 'autocomplete_analyzer';
        }

        return $res;
    }
}
