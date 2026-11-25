<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\Helpers\ImageHelper;

use function Sigmie\Functions\batch_slots;

class JinaClipApi implements EmbeddingsApi
{
    protected Client $client;

    public function __construct(
        string $baseUrl = 'http://localhost:7996',
        protected string $model = 'ViT-B/32'
    ) {
        $this->client = new Client([
            'base_uri' => rtrim($baseUrl, '/').'/',
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
        $payload = $this->requestBody([$text]);

        $response = $this->client->post('post', [
            RequestOptions::JSON => $payload,
        ]);

        return ApiResponse::resizeEmbedding(ApiResponse::jinaEmbeddings($response, 1)[0], $dimensions);
    }

    /**
     * Generate embeddings for multiple texts/images in batch
     */
    public function batchEmbed(array $payload): array
    {
        if ($payload === []) {
            return [];
        }

        foreach (batch_slots($payload, null, $this->maxBatchSize()) as $group) {
            $response = $this->client->post('post', [
                RequestOptions::JSON => $this->requestBody($group['texts']),
            ]);

            foreach (ApiResponse::jinaEmbeddings($response, count($group['keys'])) as $index => $vector) {
                $payload[$group['keys'][$index]]['vector'] = ApiResponse::resizeEmbedding($vector, $group['dims']);
            }
        }

        return $payload;
    }

    /**
     * Generate embeddings asynchronously
     */
    public function promiseEmbed(string $text, int $dimensions): PromiseInterface
    {
        return $this->client->postAsync('post', [
            RequestOptions::JSON => $this->requestBody([$text]),
        ])->then(static function (ResponseInterface $response): ResponseInterface {
            ApiResponse::jinaEmbeddings($response, 1);

            return $response;
        });
    }

    public function model(): string
    {
        return $this->model;
    }

    public function maxBatchSize(): int
    {
        return 2048;
    }

    private function requestBody(array $texts): array
    {
        return ['data' => array_map($this->input(...), $texts), 'execEndpoint' => '/'];
    }

    private function input(string $text): array
    {
        if (ImageHelper::isFilePath($text)) {
            return ['blob' => ImageHelper::toBase64(ImageHelper::fetchImageContent($text))];
        }

        $uri = ImageHelper::base64ImageUri($text) ?? (ImageHelper::isUrl($text) ? $text : null);

        return $uri === null ? ['text' => $text] : ['uri' => $uri];
    }
}
