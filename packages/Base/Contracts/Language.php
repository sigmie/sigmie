<?php declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Sigmie\Support\Contracts\Collection;

interface Language
{
    public function filters(): Collection;

    // public function stopwords(): Stopwords;

    // public function stemmers(): array;

    // public function normalizer(): string;

    // public function lowercase(): string;
}
