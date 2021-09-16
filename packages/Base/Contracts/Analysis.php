<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Support\Contracts\Collection;

interface Analysis extends Raw
{
    public function hasTokenizer(string $tokenizerName): bool;

    public function hasFilter(string $filterName): bool;

    public function hasCharFilter(string $charFilterName): bool;

    public function hasAnalyzer(string $analyzerName): bool;

    public function defaultAnalyzer(): DefaultAnalyzer;

    public function analyzers(): Collection;

    public function filters(): Collection;

    public function charFilters(): Collection;

    public function tokenizers(): Collection;

    public function addAnalyzers(array|Collection $analyzers): void;

    public function updateAnalyzers(array|Collection $oldAnalyzers): void;

    public function updateFilters(array|Collection $filters): void;

    public function updateTokenizers(array|Collection $tokenizers): void;

    public function updateCharFilters(array|Collection $charFilters): void;
}
