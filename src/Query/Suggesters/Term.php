<?php

declare(strict_types=1);

namespace Sigmie\Query\Suggesters;

use Sigmie\Query\Suggesters\Enums\SuggesterMode;
use Sigmie\Query\Suggesters\Enums\SuggesterSort;
use Sigmie\Query\Suggesters\Enums\SuggesterType;

class Term extends Suggester
{
    protected string $text;

    protected SuggesterSort $sort = SuggesterSort::Score;

    protected SuggesterMode $mode = SuggesterMode::Always;

    public function __construct(protected string $name)
    {
        parent::__construct($name);
    }

    public function sortByScore(): self
    {
        $this->sort = SuggesterSort::Score;

        return $this;
    }

    public function sortByFrequency(): self
    {
        $this->sort = SuggesterSort::Frequency;

        return $this;
    }

    public function type(): SuggesterType
    {
        return SuggesterType::Term;
    }

    public function text(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    // terms that aren't in the index
    public function missingMode(): self
    {
        $this->mode = SuggesterMode::Missing;

        return $this;
    }

    // occure more frequently in the index
    public function popularMode(): self
    {
        $this->mode = SuggesterMode::Popular;

        return $this;
    }

    // always suggest terms
    public function alwaysMode(): self
    {
        $this->mode = SuggesterMode::Always;

        return $this;
    }

    public function toRaw(): array
    {
        $res = parent::toRaw();
        $res[$this->name][$this->type()->value]['sort'] = $this->sort->value;
        $res[$this->name][$this->type()->value]['suggest_mode'] = $this->mode->value;

        if ($this->text ?? false) {
            $res[$this->name]['text'] = $this->text;
        }

        return $res;
    }
}
