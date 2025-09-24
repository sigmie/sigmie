<?php

declare(strict_types=1);

namespace Sigmie\Rag;

abstract class LLMAnswer
{
    public function __construct(
        protected string $model,
        protected array $request,
        protected array $response,
    ) {}

    public function model(): string
    {
        return $this->model;
    }

    abstract public function __toString(): string;
}
