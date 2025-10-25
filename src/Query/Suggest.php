<?php

declare(strict_types=1);

namespace Sigmie\Query;

use Sigmie\Query\Suggesters\Completion;
use Sigmie\Query\Suggesters\Phrase;
use Sigmie\Query\Suggesters\Suggester;
use Sigmie\Query\Suggesters\Term;
use Sigmie\Shared\Collection;

class Suggest
{
    protected array $suggest = [];

    protected string $text;

    public function text(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function term(string $name): Term
    {
        $suggester = new Term($name);

        $this->suggest[] = $suggester;

        return $suggester;
    }

    public function phrase(string $name): Phrase
    {
        $suggester = new Phrase($name);

        $this->suggest[] = $suggester;

        return $suggester;
    }

    public function completion(string $name): Completion
    {
        $suggester = new Completion($name);

        $this->suggest[] = $suggester;

        return $suggester;
    }

    public function toRaw(): array
    {
        $suggesters = (new Collection($this->suggest))
            ->mapToDictionary(fn (Suggester $value): array => $value->toRaw())
            ->toArray();

        if ($this->text ?? false) {
            $suggesters['text'] = $this->text;
        }

        return $suggesters;
    }
}
