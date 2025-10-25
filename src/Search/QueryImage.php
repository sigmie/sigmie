<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Stringable;

class QueryImage implements Stringable
{
    public function __construct(private string $imageSource, private float $weight = 1.0, private ?int $dimension = null, private ?array $vector = null, private ?array $fields = null) {}

    public function imageSource(): string
    {
        return $this->imageSource;
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

    public function fields(): ?array
    {
        return $this->fields;
    }

    public function hasFields(): bool
    {
        return $this->fields !== null;
    }

    public function toArray(): array
    {
        return [
            'imageSource' => $this->imageSource,
            'weight' => $this->weight,
            'dimension' => $this->dimension,
            'vector' => $this->vector,
            'fields' => $this->fields,
        ];
    }

    public function __toString(): string
    {
        return $this->imageSource;
    }
}
