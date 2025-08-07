<?php

declare(strict_types=1);

namespace Sigmie\Search;

class QueryString
{
    private string $text;
    private float $weight;
    private ?int $dimension = null;
    private ?array $vector = null;

    public function __construct(
        string $text,
        float $weight = 1.0,
        ?int $dimension = null,
        ?array $vector = null
    ) {
        $this->text = $text;
        $this->weight = $weight;
        $this->dimension = $dimension;
        $this->vector = $vector;
    }

    public function text(): string
    {
        return $this->text;
    }

    public function weight(): float
    {
        return $this->weight;
    }

    public function dimension(): ?int
    {
        return $this->dimension;
    }

    public function vector(): ?array
    {
        return $this->vector;
    }

    public function setDimension(int $dimension): self
    {
        $this->dimension = $dimension;
        return $this;
    }

    public function setVector(array $vector): self
    {
        $this->vector = $vector;
        return $this;
    }

    public function hasVector(): bool
    {
        return $this->vector !== null;
    }

    public function hasDimension(): bool
    {
        return $this->dimension !== null;
    }

    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'weight' => $this->weight,
            'dimension' => $this->dimension,
            'vector' => $this->vector,
        ];
    }

    public function __toString(): string
    {
        return $this->text;
    }
}