<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Enums\SearchEngineType;
use Sigmie\Mappings\ElasticsearchMappingType;
use stdClass;

class FlatObject extends Type
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->type = ElasticsearchMappingType::FLAT_OBJECT->value;
    }

    public function toRaw(?SearchEngine $driver = null): array
    {
        if ($driver?->engine() === SearchEngineType::Elasticsearch) {
            $raw = parent::toRaw();
            $raw[$this->name]['type'] = 'flattened';

            return $raw;
        }

        return [$this->name => ['type' => 'flat_object']];
    }

    public function validate(string $key, mixed $value, SearchEngineType $engine = SearchEngineType::Elasticsearch): array
    {
        if (! is_array($value) && ! is_object($value)) {
            return [false, sprintf('The field %s mapped as flat_object must be an object or array', $key)];
        }

        if ($engine === SearchEngineType::Elasticsearch && ! self::acceptsFlattenedValue(is_array($value) ? self::sourceRecords($value) : $value)) {
            return [false, sprintf('The field %s exceeds flattened indexing limits or has an invalid key', $key)];
        }

        return [true, ''];
    }

    public static function sourceRecords(array $value): array|stdClass
    {
        if (! array_is_list($value)) {
            return $value;
        }

        foreach ($value as $item) {
            if ($item !== null && ! is_array($item) && ! is_object($item)) {
                return (object) $value;
            }
        }

        return array_map(fn ($item) => is_array($item) ? self::sourceRecords($item) : $item, $value);
    }

    public static function acceptsFlattenedValue(mixed $value, array $options = [], ?string $path = null, int $depth = 1): bool
    {
        if (! ($options['index'] ?? true) && ! ($options['doc_values'] ?? true)) {
            return true;
        }

        if (is_array($value) && array_is_list($value)) {
            foreach ($value as $item) {
                if (! self::acceptsFlattenedValue($item, $options, $path, $depth)) {
                    return false;
                }
            }

            return true;
        }

        if (is_array($value) || is_object($value)) {
            if ($depth > ($options['depth_limit'] ?? 20)) {
                return false;
            }

            foreach ((array) $value as $key => $item) {
                $name = $path === null ? (string) $key : $path.'.'.$key;
                if (! self::acceptsFlattenedValue($item, $options, $name, $depth + 1)) {
                    return false;
                }
            }

            return true;
        }

        $value ??= $options['null_value'] ?? null;
        if ($value === null) {
            return true;
        }

        if ($path === null || str_contains($path, "\0")) {
            return false;
        }

        $text = is_string($value) ? $value : json_encode($value);
        if (strlen($path) + 1 + strlen($text) <= 32766) {
            return true;
        }

        return isset($options['ignore_above']) && strlen(mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')) / 2 > $options['ignore_above'];
    }
}
