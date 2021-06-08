<?php

namespace Sigmie\Base\Contracts;

use Sigmie\Support\Contracts\Collection;

interface Analyzer extends Name
{
    public function tokenizer(): Tokenizer;

    public function tokenFilters(): Collection;

    public function charFilters(): Collection;
}
