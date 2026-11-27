<?php

declare(strict_types=1);

namespace Sigmie\Base;

use Exception;
use Http\Client\Exception as HttpClientException;
use Sigmie\Shared\Contracts\ToRaw;
use stdClass;

use function Sigmie\Functions\response_value;

class ElasticsearchException extends Exception implements HttpClientException, ToRaw
{
    public function __construct(public array $json, int $code)
    {
        $encoded = json_encode($json, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);

        parent::__construct(is_string($encoded) ? $encoded : '', $code);
    }

    public function json(null|int|string $key = null): int|bool|string|array|null|float
    {
        $value = response_value($this->json, $key === null ? null : (string) $key);

        return $value instanceof stdClass ? (array) $value : $value;
    }

    public function toRaw(): array
    {
        return $this->json;
    }
}
