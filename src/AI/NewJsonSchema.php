<?php

declare(strict_types=1);

namespace Sigmie\AI;

class NewJsonSchema
{
    protected array $properties = [];

    protected array $required = [];

    protected string $name = 'response';

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function string(string $name, bool $required = true): self
    {
        $this->properties[$name] = ['type' => 'string'];

        if ($required) {
            $this->required[] = $name;
        }

        return $this;
    }

    public function number(string $name, bool $required = true): self
    {
        $this->properties[$name] = ['type' => 'number'];

        if ($required) {
            $this->required[] = $name;
        }

        return $this;
    }

    public function array(string $name, callable $items, bool $required = true): self
    {
        $itemSchema = new NewJsonSchema;
        $items($itemSchema);

        $this->properties[$name] = [
            'type' => 'array',
            'items' => $itemSchema->toItemSchema(),
            'additionalProperties' => false,
        ];

        if ($required) {
            $this->required[] = $name;
        }

        return $this;
    }

    public function object(string $name, callable $callback, bool $required = true): self
    {
        $objectSchema = new NewJsonSchema;
        $callback($objectSchema);

        $this->properties[$name] = $objectSchema->toObjectSchema();

        if ($required) {
            $this->required[] = $name;
        }

        return $this;
    }

    protected function toObjectSchema(): array
    {
        $schema = [
            'type' => 'object',
            'properties' => $this->properties,
            'additionalProperties' => false,
        ];

        if ($this->required !== []) {
            $schema['required'] = $this->required;
        }

        return $schema;
    }

    protected function toItemSchema(): array
    {
        return $this->toObjectSchema();
    }

    public function toArray(): array
    {
        $schema = [
            'type' => 'object',
            'properties' => $this->properties,
            'additionalProperties' => false,
        ];

        if ($this->required !== []) {
            $schema['required'] = $this->required;
        }

        return $schema;
    }
}
