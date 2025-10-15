<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\RequestOptions;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\Helpers\ImageHelper;

class JinaClipApi implements EmbeddingsApi
{
    protected Client $client;

    protected string $model;

    public function __construct(
        string $baseUrl = 'http://localhost:7996',
        string $model = 'ViT-B/32'
    ) {
        $this->model = $model;
        $this->client = new Client([
            'base_uri' => $baseUrl,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 120,
        ]);
    }

    /**
     * Generate embeddings for a single text or image
     */
    public function embed(string $text, int $dimensions): array
    {
        $isImage = $this->isImageSource($text);

        $payload = [
            'data' => [
                [
                    'text' => !$isImage ? $text : null,
                    'uri' => $isImage ? $text : null,
                ]
            ]
        ];

        $response = $this->client->post('/encode', [
            RequestOptions::JSON => $payload
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return $data['data'][0]['embedding'] ?? [];
    }

    /**
     * Generate embeddings for multiple texts/images in batch
     */
    public function batchEmbed(array $payload): array
    {
        if (count($payload) === 0) {
            return [];
        }

        $jinaData = [];

        foreach ($payload as $item) {
            $text = $item['text'] ?? '';
            $isImage = $this->isImageSource($text);

            $jinaData[] = [
                'text' => !$isImage ? $text : null,
                'uri' => $isImage ? $text : null,
            ];
        }

        $response = $this->client->post('/encode', [
            RequestOptions::JSON => [
                'data' => $jinaData
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        // Map embeddings back to payload
        foreach ($data['data'] as $index => $result) {
            if (isset($result['embedding'])) {
                $payload[$index]['vector'] = $result['embedding'];
            }
        }

        return $payload;
    }

    /**
     * Generate embeddings asynchronously
     */
    public function promiseEmbed(string $text, int $dimensions): Promise
    {
        $isImage = $this->isImageSource($text);

        return $this->client->postAsync('/encode', [
            RequestOptions::JSON => [
                'data' => [
                    [
                        'text' => !$isImage ? $text : null,
                        'uri' => $isImage ? $text : null,
                    ]
                ]
            ]
        ]);
    }

    public function model(): string
    {
        return $this->model;
    }

    /**
     * Check if a string is likely an image source (URL, base64, or file path)
     */
    protected function isImageSource(string $text): bool
    {
        return ImageHelper::isUrl($text) ||
               ImageHelper::isBase64($text) ||
               ImageHelper::isFilePath($text);
    }
}