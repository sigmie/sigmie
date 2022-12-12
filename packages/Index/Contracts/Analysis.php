<?php

declare(strict_types=1);

namespace Sigmie\Index\Contracts;

use Sigmie\Index\Analysis\DefaultAnalyzer;
use Sigmie\Shared\Contracts\FromRaw;
use Sigmie\Shared\Contracts\ToRaw;

interface Analysis extends FromRaw, ToRaw
{
    public function hasTokenizer(string $tokenizerName): bool;

    public function hasFilter(string $filterName): bool;

    public function hasCharFilter(string $charFilterName): bool;

    public function hasAnalyzer(string $analyzerName): bool;

    public function defaultAnalyzer(): DefaultAnalyzer;

    public function addAnalyzer(Analyzer $analyzer): void;

    public function addNormalizer(Normalizer $normalizer): void;

    public function analyzers(): array;

    public function filters(): array;

    public function charFilters(): array;

    public function tokenizers(): array;

    public function addAnalyzers(array $analyzers): void;

    public function addFilters(array $filters): void;

    public function addTokenizers(array $tokenizers): void;

    public function addTokenizer(Tokenizer $tokenizer): void;

    public function addCharFilters(array $charFilters): void;
}
