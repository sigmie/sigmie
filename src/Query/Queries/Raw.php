<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries;

use stdClass;

class Raw extends Query
{
    public function __construct(protected string|array $raw) {}

    public function toRaw(): array|stdClass
    {
        if (is_array($this->raw)) {
            return $this->boost === 1.0 ? $this->raw : $this->wrapWithBoost($this->raw);
        }

        $decoded = json_decode($this->raw);

        $needsOriginalJson = in_array(json_last_error(), [JSON_ERROR_INVALID_PROPERTY_NAME, JSON_ERROR_DEPTH], true);

        if (! $needsOriginalJson && ! $decoded instanceof stdClass && ! is_array($decoded)) {
            return [$this->raw];
        }

        $query = $this->preserveJsonKeys($decoded, $needsOriginalJson);

        // Let Elasticsearch read JSON that PHP cannot round-trip without changing its meaning.
        if ($needsOriginalJson || preg_match('/[:,\[]\s*-0\s*[,}\]]/', $this->raw)) {
            $query = ['wrapper' => ['query' => base64_encode($this->raw)]];
        }

        return $this->boost === 1.0 ? $query : $this->wrapWithBoost($query);
    }

    private function preserveJsonKeys(mixed $value, bool &$needsOriginalJson): mixed
    {
        if ($value instanceof stdClass) {
            $array = [];

            foreach (get_object_vars($value) as $key => $item) {
                $array[$key] = $this->preserveJsonKeys($item, $needsOriginalJson);
            }

            if ($array === []) {
                return (object) [];
            }

            if (! array_is_list($array)) {
                return $array;
            }

            return (object) $array;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->preserveJsonKeys($item, $needsOriginalJson);
            }
        }

        // Elasticsearch can read numeric tokens as text, including trailing zeros.
        if (is_float($value)) {
            $needsOriginalJson = true;
        }

        return $value;
    }
}
