<?php

declare(strict_types=1);

namespace Sigmie\Query\Suggesters;

use Sigmie\Query\Suggesters\Enums\SuggesterType;

class Phrase extends Suggester
{
    protected string $text;

    protected int $ngramSize = 1;

    protected array $highlight;

    public function __construct(protected string $name)
    {
        parent::__construct($name);
    }

    public function type(): SuggesterType
    {
        return SuggesterType::Phrase;
    }

    public function ngramSize(int $ngramSize): self
    {
        $this->ngramSize = $ngramSize;

        return $this;
    }

    public function highlight(string $preTag, string $postTag): self
    {
        $this->highlight = [
            'pre_tag' => $preTag,
            'post_tag' => $postTag,
        ];

        return $this;
    }

    public function text(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function toRaw(): array
    {
        $res = parent::toRaw();

        $res[$this->name][$this->type()->value]['gram_size'] = $this->ngramSize;

        if ($this->text ?? false) {
            $res[$this->name]['text'] = $this->text;
        }

        if ($this->highlight ?? false) {
            $res[$this->name][$this->type()->value]['highlight'] = $this->highlight;
        }

        return $res;
    }
}
