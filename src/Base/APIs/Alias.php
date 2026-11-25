<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Base\Http\ElasticsearchRequest;

trait Alias
{
    use API;

    public function aliasAPICall(string $method, array $body): ElasticsearchResponse
    {
        $uri = new Uri('/_aliases');

        $esRequest = new ElasticsearchRequest($method, $uri, $body);

        $response = $this->elasticsearchCall($esRequest);

        if ($response->json('errors') === true) {
            $failure = current(array_filter(
                $response->json('action_results') ?? [],
                fn (array $result): bool => isset($result['error']) || ($result['status'] ?? 200) >= 400,
            ));

            throw new ElasticsearchException(
                ['type' => $failure['error']['type'] ?? 'alias_action_failed', ...$response->json()],
                $failure['status'] ?? $response->code(),
            );
        }

        return $response;
    }
}
