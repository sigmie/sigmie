<?php

namespace Sigmie\Base\Contracts;

use Sigmie\Base\Analysis\TokenFilter\Stopwords;

interface Language
{
    public function stopwords(): Stopwords;

    public function stemmers(): array;

    // public function normalizer(): string;

    // public function lowercase(): string;
}
