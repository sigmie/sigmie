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
            ->mapToDictionary(fn (Suggester $value): array => $value instanceof Completion
                ? $value->toRaw($this->text ?? '')
                : $value->toRaw())
            ->toArray();

        if (! isset($this->text)) {
            return $suggesters;
        }

        // `text` is both the global text and a legal suggester name.
        // A string would replace the suggester, so the text moves onto each
        // term and phrase that does not already carry its own.
        if (isset($suggesters['text']) && is_array($suggesters['text'])) {
            foreach ($suggesters as $name => $body) {
                if (! is_array($body)) {
                    continue;
                }

                if (array_key_exists('text', $body)) {
                    continue;
                }

                if (! isset($body['term']) && ! isset($body['phrase'])) {
                    continue;
                }

                $suggesters[$name]['text'] = $this->text;
            }

            return $suggesters;
        }

        $suggesters['text'] = $this->text;

        return $suggesters;
    }
}
