<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Responses;

use Sigmie\Base\ElasticsearchException;
use Sigmie\Base\Http\ElasticsearchResponse;

class Reindex extends ElasticsearchResponse
{
    public static function assertComplete(array $payload, int $httpCode): void
    {
        if (($payload['completed'] ?? null) === false) {
            return;
        }

        $result = $payload['response'] ?? $payload;
        if (($result['failures'] ?? []) === [] && ($result['version_conflicts'] ?? 0) === 0
            && ($result['timed_out'] ?? false) === false && ! isset($result['canceled'])
            && ! ($result['cancelled'] ?? false) && ! ($payload['task']['cancelled'] ?? false)
            && ! isset($result['error']) && ! isset($payload['error'])) {
            return;
        }

        throw new ElasticsearchException($payload, $result['failures'][0]['status'] ?? $httpCode);
    }
}
