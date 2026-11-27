<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Traits;

use Closure;
use Sigmie\Index\Contracts\Analyzer;
use Sigmie\Index\NewAnalyzer;

trait HasAnalyzer
{
    protected ?Analyzer $analyzer = null;

    protected bool $explicitAnalyzer = false;

    public bool $hasAnalyzerCallback = false;

    public Closure $newAnalyzerClosure;

    protected ?string $searchAnalyzer = null;

    protected bool $explicitSearchAnalyzer = false;

    protected bool $searchSynonyms = false;

    public function searchSynonyms(bool $value = true): static
    {
        $this->searchSynonyms = $value;

        return $this;
    }

    protected function initAnalyzer(): void
    {
        $this->newAnalyzerClosure = fn () => null;
    }

    public function analysisFromCallback(NewAnalyzer $newAnalyzer): void
    {
        ($this->newAnalyzerClosure)($newAnalyzer);
    }

    public function withNewAnalyzer(Closure $closure): static
    {
        $this->explicitAnalyzer = false;
        $this->hasAnalyzerCallback = true;
        $this->newAnalyzerClosure = $closure;

        return $this;
    }

    public function newAnalyzer(Closure $callable): void
    {
        $this->explicitAnalyzer = false;
        $this->hasAnalyzerCallback = true;
        $this->newAnalyzerClosure = $callable;
    }

    public function withAnalyzer(Analyzer $analyzer, ?string $searchAnalyzer = null): void
    {
        $this->explicitAnalyzer = true;
        $this->analyzer = $analyzer;
        $this->searchAnalyzer = $searchAnalyzer ?? $analyzer->name();
        $this->explicitSearchAnalyzer = $searchAnalyzer !== null;
    }

    public function searchAnalyzer(): string
    {
        return $this->searchSynonyms ? 'default_with_synonyms' : ($this->searchAnalyzer ?? 'default');
    }

    public function analyzer(): ?Analyzer
    {
        return $this->analyzer;
    }
}
