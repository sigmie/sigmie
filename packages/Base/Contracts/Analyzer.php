<?php

namespace Sigmie\Base\Contracts;

use Sigmie\Support\Contracts\Collection;

interface Analyzer extends Name
{
    public function tokenizer(): Tokenizer;

    public function filters(): Collection;

    public function updateTokenizer(Tokenizer $tokenizer): void;

    public function addFilters(Collection|array $filters): void;

    public function addCharFilters(Collection|array $charFilters): void;

    public function removeCharFilter(string $name): void;

    public function removeFilter(string $name): void;

    public function charFilters(): Collection;
}
