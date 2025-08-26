<?php

declare(strict_types=1);

namespace Sigmie\AI\EmbeddingProviders;

use Http\Promise\Promise;
use GuzzleHttp\Psr7\Uri;
use Sigmie\AI\Contracts\EmbeddingProvider;
use Sigmie\Http\JSONClient;
use Sigmie\Http\JSONRequest;

class SigmieProvider implements EmbeddingProvider
{
    protected JSONClient $http;
    protected string $model = 'sigmie-embeddings';

    public function __construct()
    {
        $this->http = JSONClient::create([
            'https://ai-b.sigmie.app',
        ]);
    }

    public function embed(string $text, int $dimensions): array
    {
        $response = $this->http->request(new JSONRequest(
            'POST',
            new Uri('/embeddings'),
            [
                [
                    'text' => $text,
                    'dims' => (string) $dimensions
                ]
            ]
        ));

        return $response->json('0.embeddings');
    }

    public function batchEmbed(array $payload): array
    {
        if (count($payload) === 0) {
            return [];
        }

        $response = $this->http->request(new JSONRequest(
            'POST',
            new Uri('/embeddings'),
            $payload
        ));

        foreach ($response->json() as $index => $result) {
            $payload[$index]['vector'] = dot($result)->get('embeddings');
        }

        return $payload;
    }

    public function promiseEmbed(string $text, int $dimensions): Promise
    {
        return $this->http->promise(new JSONRequest(
            'POST',
            new Uri('/embeddings'),
            [
                'text' => $text,
                'dims' => (string) $dimensions
            ]
        ));
    }

    public function getModel(): string
    {
        return $this->model;
    }
}