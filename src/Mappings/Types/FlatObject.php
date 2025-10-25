<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\ElasticsearchMappingType;

class FlatObject extends Type
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->type = ElasticsearchMappingType::FLAT_OBJECT->value;
    }


    public function validate(string $key, mixed $value): array
    {
        if (! is_array($value) && ! is_object($value)) {
            return [false, sprintf('The field %s mapped as flat_object must be an object or array', $key)];
        }

        return [true, ''];
    }
}