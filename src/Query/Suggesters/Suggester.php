<?php

declare(strict_types=1);

namespace Sigmie\Query\Suggesters;

use Sigmie\Query\Suggesters\Enums\SuggesterType;
use Sigmie\Shared\Contracts\ToRaw;

abstract class Suggester implements ToRaw
{
    protected string $field;

    protected int $size = 5;

    public function __construct(protected string $name)
    {
    }

    abstract public function type(): SuggesterType;

    public function field(string $field): static
    {
        $this->field = $field;

        return $this;
    }

    public function size(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function toRaw(): array
    {
        return [
            $this->name => [
                $this->type()->value => [
                    'field' => $this->field,
                    'size' => (string) $this->size,
                ],
            ],
        ];
    }
}
