<?php

declare(strict_types=1);

namespace Sigmie\AI\Contracts;

interface LLM
{
    /**
     * Generate an answer based on input and instructions
     */
    public function answer(
        string $input,
        string $instructions,
        int $maxTokens,
        float $temperature
    ): array;
}
