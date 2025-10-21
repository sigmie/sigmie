<?php

declare(strict_types=1);

namespace Sigmie\Classification;

class ClassificationResult
{
    public function __construct(
        protected string $label,
        protected float $confidence,
        protected array $allScores
    ) {}

    public function label(): string
    {
        return $this->label;
    }

    public function confidence(): float
    {
        return $this->confidence;
    }

    public function allScores(): array
    {
        return $this->allScores;
    }

    public function score(string $label): ?float
    {
        return $this->allScores[$label] ?? null;
    }
}
