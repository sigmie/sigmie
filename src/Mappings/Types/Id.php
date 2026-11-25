<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

class Id extends CaseSensitiveKeyword
{
    public function toRaw(): array
    {
        $raw = parent::toRaw();

        $raw[$this->name]['fields'] = [
            ...(array) ($raw[$this->name]['fields'] ?? []),
            ...(new Number('sortable'))->long()->toRaw(),
        ];

        return $raw;
    }

    public function sortableName(): ?string
    {
        return $this->fullPath().'.sortable';
    }

    public function validate(string $key, mixed $value): array
    {
        if (! is_int($value)) {
            return [false, sprintf('The field %s mapped as %s must be an integer', $key, $this->typeName())];
        }

        return [true, ''];
    }

    public function typeName(): string
    {
        return 'identifier';
    }
}
