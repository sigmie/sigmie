<?php

declare(strict_types=1);

namespace Sigmie\AI\Contracts;

interface LLMAnswer
{
    public function model(): string;

    public function __toString(): string;
}
