<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\Client;

abstract class AbstractOpenAIApi
{
    protected Client $client;

    public function __construct(
        string $apiKey,
        protected string $model,
        string $baseUri = 'https://api.openai.com',
        int $timeout = 60
    ) {
        $this->client = new Client([
            'base_uri' => $baseUri,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => $timeout,
        ]);
    }

    public function model(): string
    {
        return $this->model;
    }
}
