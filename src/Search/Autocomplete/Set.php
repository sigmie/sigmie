<?php

declare(strict_types=1);

namespace Sigmie\Search\Autocomplete;

use Sigmie\Base\APIs\API;

class Set extends Processor
{
    use API;

    protected string $field;

    protected mixed $value;

    protected function type(): string
    {
        return 'set';
    }

    protected function values(): array
    {
        return [
            'field' => $this->field,
            'value' => $this->value,
        ];
    }

    public function field(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function value(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }
}
