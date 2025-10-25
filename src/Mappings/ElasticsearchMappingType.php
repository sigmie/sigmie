<?php

namespace Sigmie\Mappings;

enum ElasticsearchMappingType: string
{
    case KEYWORD = 'keyword';
    case INTEGER = 'integer';
    case LONG = 'long';
    case FLOAT = 'float';
    case SCALED_FLOAT = 'scaled_float';
    case BOOLEAN = 'boolean';
    case DATE = 'date';
    case PROPERTIES = 'properties';
    case TEXT = 'text';
    case SEARCH_AS_YOU_TYPE = 'search_as_you_type';
    case COMPLETION = 'completion';
    case DOUBLE = 'double';
    case FLAT_OBJECT = 'flat_object';
    case INTEGER_RANGE = 'integer_range';
    case FLOAT_RANGE = 'float_range';
    case LONG_RANGE = 'long_range';
    case DOUBLE_RANGE = 'double_range';
    case DATE_RANGE = 'date_range';
    case IP_RANGE = 'ip_range';

    public function isKeyword(string $type): bool
    {
        return $type === $this::KEYWORD->value;
    }

    public function isInteger(string $type): bool
    {
        return $type === $this::INTEGER->value;
    }

    public function isLong(string $type): bool
    {
        return $type === $this::LONG->value;
    }

    public function isFloat(string $type): bool
    {
        return $type === $this::FLOAT->value;
    }

    public function isBoolean(string $type): bool
    {
        return $type === $this::BOOLEAN->value;
    }

    public function isDate(string $type): bool
    {
        return $type === $this::DATE->value;
    }
}
