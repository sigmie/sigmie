<?php

declare(strict_types=1);

namespace Sigmie\Search\Contracts;

interface SearchBuilder
{
    public function typoTolerance(int $oneTypoChars = 3, int $twoTypoChars = 6): static;

    public function size(int $size = 20): static;

    public function from(int $from = 0): static;

    public function minCharsForOneTypo(int $chars): static;

    public function minCharsForTwoTypo(int $chars): static;

    public function weight(array $weight): static;

    public function retrieve(array $attributes): static;

    public function highlighting(array $attributes, string $prefix, string $suffix): static;

    public function typoTolerantAttributes(array $attributes): static;

    public function fields(array $fields): static;
}
