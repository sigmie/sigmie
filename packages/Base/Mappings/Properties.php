<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings;

use Sigmie\Support\Collection;

class Properties
{
    public function __construct(protected array $fields = []){}

    public function raw(): array
    {
        $fields = new Collection($this->fields);
        $fields = $fields->mapToDictionary(function ($value) {
            return [$value->name() => $value->raw()];
        })->toArray();

        return $fields;
    }
}
