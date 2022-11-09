<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchRequest;

trait Render
{
    use API;

    protected function renderAPICall(string $name, array $params = []): ElasticsearchResponse
    {
        $uri = new Uri("/_render/template");

        $body = [
            "id" => $name,
            "params" => (object) $params
        ];

        $esRequest = new ElasticsearchRequest('POST', $uri, $body);

        return $this->elasticsearchCall($esRequest);
    }
}
