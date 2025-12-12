<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Traits;

use Closure;
use Sigmie\Index\Contracts\Analyzer;
use Sigmie\Index\NewAnalyzer;

trait HasAnalyzer
{
    protected ?Analyzer $analyzer = null;

    public bool $hasAnalyzerCallback = false;

    public Closure $newAnalyzerClosure;

    protected string $searchAnalyzer = 'default';

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
        $this->hasAnalyzerCallback = true;
        $this->newAnalyzerClosure = $closure;

        return $this;
    }

    public function newAnalyzer(Closure $callable): void
    {
        $this->hasAnalyzerCallback = true;
        $this->newAnalyzerClosure = $callable;
    }

    public function withAnalyzer(Analyzer $analyzer): void
    {
        $this->analyzer = $analyzer;
        $this->searchAnalyzer = $analyzer->name();
    }

    public function searchAnalyzer(): string
    {
        return $this->searchAnalyzer;
    }

    public function analyzer(): ?Analyzer
    {
        return $this->analyzer;
    }
}
