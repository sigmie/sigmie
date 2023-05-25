<?php

declare(strict_types=1);

namespace Sigmie\Query\Suggesters;

use Sigmie\Query\Suggesters\Enums\SuggesterType;
use Sigmie\Shared\Contracts\ToRaw;

abstract class Suggester implements ToRaw
{
    protected string $field;

    protected int $size;

    public function __construct(protected string $name)
    {
        $this->size = 5;
    }

    abstract public function type(): SuggesterType;

    public function field(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function size(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function toRaw(): array
    {
        $res = [
            $this->name => [
                $this->type()->value => [
                    'field' => $this->field,
                    'size' => (string) $this->size,
                ],
            ],
        ];

        if ($res[$this->name][$this->type()->value] === 'completion') {
            $res[$this->name][$this->type()->value]['skip_duplicates'] = true;
        }

        return $res;
    }
}
