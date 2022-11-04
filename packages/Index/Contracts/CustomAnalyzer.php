<?php

declare(strict_types=1);

namespace Sigmie\Index\Contracts;

use Sigmie\Index\Contracts\Analyzer;
use Sigmie\Shared\Collection;

interface CustomAnalyzer extends Analyzer
{
    public static function create(
        array $raw,
        array $charFilters,
        array $filters,
        array $tokenizers
    ): static;

    public function tokenizer(): null|Tokenizer;

    public function filters(): array;

    public function setTokenizer(Tokenizer $tokenizer): void;

    public function addFilters(array $filters): void;

    public function addCharFilters(array $charFilters): void;

    public function removeCharFilter(string $name): void;

    public function removeFilter(string $name): void;

    public function charFilters(): array;
}
