<?php

namespace Sigmie\Search;

use GuzzleHttp\Client;
use Sigmie\Search\FailedOperation;

abstract class BaseService
{
    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    private function execute(...$params): array
    {
        $response = $this->client->request(...$params);

        return [
            $response->getStatusCode(),
            json_decode($response->getBody()->getContents(), true)
        ];
    }

    protected function call($params, $expected)
    {
        [$code, $json] = $this->execute(...$params);

        if ($code >= 200 && $code < 300) {
            return new $expected($json);
        }

        return new FailedOperation($json);
    }
}
