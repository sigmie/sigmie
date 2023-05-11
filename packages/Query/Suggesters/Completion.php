<?php

declare(strict_types=1);

namespace Sigmie\Query\Suggesters;

use Sigmie\Query\Suggesters\Enums\SuggesterType;

class Completion extends Suggester
{
    protected string $prefix = '';

    protected string $analyzer;

    protected bool $fuzzy = false;

    public function type(): SuggesterType
    {
        return SuggesterType::Completion;
    }

    public function analyzer(string $analyzer): self
    {
        $this->analyzer = $analyzer;

        return $this;
    }

    public function prefix(string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function fuzzy(): static
    {
        $this->fuzzy = true;

        return $this;
    }

    public function toRaw(): array
    {
        $res = parent::toRaw();

        if ($this->prefix ?? false) {
            $res[$this->name]['prefix'] = $this->prefix;
        }

        if ($this->fuzzy ?? false) {
            $res[$this->name][$this->type()->value]['fuzzy'] = true;
        }

        if ($this->analyzer ?? false) {
            $res[$this->name][$this->type()->value]['analyzer'] = 'autocomplete_analyzer';
        }

        if ($this->analyzer ?? false) {
            $res[$this->name][$this->type()->value]['skip_duplicates'] = true;
        }

        return $res;
    }
}
