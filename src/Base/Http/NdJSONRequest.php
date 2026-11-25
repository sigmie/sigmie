<?php

declare(strict_types=1);

namespace Sigmie\Base\Http;

use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Http\NdJSONRequest as HttpNdJSONRequest;

abstract class NdJSONRequest extends HttpNdJSONRequest implements ElasticsearchRequest
{
    public static function encode(array $data): string
    {
        $body = '';
        foreach ($data as $line) {
            $body .= json_encode(is_array($line) ? (object) $line : $line, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR).self::SEPARATOR;
        }

        return $body;
    }

    public static function decode(string $ndjson): array
    {
        return array_map(
            static fn (string $line): mixed => json_decode($line, true, flags: JSON_BIGINT_AS_STRING),
            explode("\n", $ndjson),
        );
    }

    public function body(): ?array
    {
        $body = (string) $this->getBody();

        return $body === '' ? null : self::decode($body);
    }
}
