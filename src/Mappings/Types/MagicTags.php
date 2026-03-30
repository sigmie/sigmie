<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

class MagicTags extends Keyword
{
    protected string $llmApiName = '';

    protected int $maxTags = 5;

    public function __construct(
        string $name,
        protected string $fromField,
    ) {
        parent::__construct($name);
    }

    public function api(string $name): self
    {
        $this->llmApiName = $name;

        return $this;
    }

    public function maxTags(int $max): self
    {
        $this->maxTags = $max;

        return $this;
    }

    public function getMaxTags(): int
    {
        return $this->maxTags;
    }

    public function fromField(): string
    {
        return $this->fromField;
    }

    public function apiName(): string
    {
        return $this->llmApiName;
    }

    public function validate(string $key, mixed $value): array
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (! is_string($item)) {
                    return [false, sprintf('The field %s mapped as %s must contain only strings', $key, $this->typeName())];
                }
            }

            return [true, ''];
        }

        if (! is_string($value)) {
            return [false, sprintf('The field %s mapped as %s must be a string or array of strings', $key, $this->typeName())];
        }

        return [true, ''];
    }
}
