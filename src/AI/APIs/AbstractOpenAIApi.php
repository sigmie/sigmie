<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

abstract class AbstractOpenAIApi
{
    protected Client $client;

    protected string $model;

    public function __construct(
        string $apiKey,
        string $model,
        string $baseUri = 'https://api.openai.com',
        int $timeout = 60
    ) {
        $this->model = $model;
        $this->client = new Client([
            'base_uri' => $baseUri,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => $timeout,
        ]);
    }
}
