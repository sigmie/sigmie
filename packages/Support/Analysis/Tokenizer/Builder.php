<?php

declare(strict_types=1);

namespace Sigmie\Support\Analysis\Tokenizer;

use Sigmie\Support\Update\Update as UpdateBuilder;

class Builder
{
    public function __construct(protected UpdateBuilder $builder)
    {
    }

    public function whiteSpaces()
    {
        return $this->builder;
    }

    public function pattern()
    {
        return $this->builder;
    }

    public function wordBoundaries()
    {
        return $this->builder;
    }
}
