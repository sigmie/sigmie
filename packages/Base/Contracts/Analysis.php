<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Sigmie\Support\Contracts\Collection;

interface Analysis
{
    public function hasTokenizer(string $tokenizerName): bool;

    public function hasFilter(string $filterName): bool;

    public function hasCharFilter(string $charFilterName): bool;

    public function hasAnalyzer(string $analyzerName): bool;

    public function addAnalyzers(array|Collection $analyzers): void;

    public function updateFilters(array|Collection $filters): void;

    public function updateCharFilters(array|Collection $charFilter): void;

    public function addLanguageFilters(Language $language): static;
}
