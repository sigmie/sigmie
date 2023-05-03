<?php

declare(strict_types=1);

namespace Sigmie\Query\Suggesters;

use Sigmie\Query\Suggesters\Enums\SuggesterType;

class Completion extends Suggester
{
    protected string $prefix = '';

    public function type(): SuggesterType
    {
        return SuggesterType::Completion;
    }

    public function prefix(string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function toRaw(): array
    {
        $res = parent::toRaw();

        if ($this->prefix ?? false) {
            $res[$this->name]['prefix'] = $this->prefix;
        }

        return $res;
    }
}
