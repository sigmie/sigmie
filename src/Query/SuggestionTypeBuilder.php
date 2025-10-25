<?php

declare(strict_types=1);

namespace Sigmie\Query;

class SuggestionTypeBuilder
{
    protected string $name;

    protected string $type;

    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function term() {}
}
