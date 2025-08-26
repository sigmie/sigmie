<?php

declare(strict_types=1);

namespace Sigmie\Search;

class NewContextComposer
{
    protected int $maxContextTokens = 4000;
    protected string $citationStyle = 'numeric';
    protected array $includeMetadata = [];

    public function maxContextTokens(int $tokens): self
    {
        $this->maxContextTokens = $tokens;
        return $this;
    }

    public function citationStyle(string $style): self
    {
        $this->citationStyle = $style;
        return $this;
    }

    public function includeMetadata(array $metadata): self
    {
        $this->includeMetadata = $metadata;
        return $this;
    }

    public function getMaxContextTokens(): int
    {
        return $this->maxContextTokens;
    }

    public function getCitationStyle(): string
    {
        return $this->citationStyle;
    }

    public function getIncludeMetadata(): array
    {
        return $this->includeMetadata;
    }
}