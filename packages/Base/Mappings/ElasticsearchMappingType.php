<?php

namespace Sigmie\Base\Mappings;

enum ElasticsearchMappingType: string
{
    case KEYWORD = 'keyword';
    case INTEGER = 'integer';
    case LONG = 'long';
    case FLOAT = 'float';
    case BOOLEAN = 'boolean';
    case DATE = 'date';
    case PROPERTIES = 'properties';
    case TEXT =  'text';
    case SEARCH_AS_YOU_TYPE =  'search_as_you_type';
    case COMPLETION =  'completion';

    public function isKeyword(string $type)
    {
        return $type === $this::KEYWORD->value;
    }

    public function isInteger(string $type)
    {
        return $type === $this::INTEGER->value;
    }

    public function isLong(string $type)
    {
        return $type === $this::LONG->value;
    }

    public function isFloat(string $type)
    {
        return $type === $this::FLOAT->value;
    }

    public function isBoolean(string $type)
    {
        return $type === $this::BOOLEAN->value;
    }

    public function isDate(string $type)
    {
        return $type === $this::DATE->value;
    }
}
