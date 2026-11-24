<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\RequestOptions;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\Helpers\ImageHelper;

use function Sigmie\Functions\batch_slots;

class InfinityClipApi implements EmbeddingsApi
{
    protected Client $client;

    public function __construct(
        string $baseUrl = 'http://localhost:7996',
        protected string $model = 'wkcn/TinyCLIP-ViT-8M-16-Text-3M-YFCC15M'
    ) {
        $this->client = new Client([
            'base_uri' => rtrim($baseUrl, '/').'/',
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 120, // Longer timeout for image processing
        ]);
    }

    public function embed(string $text, int $dimensions): array
    {
        $response = $this->client->post('embeddings', [
            RequestOptions::JSON => $this->singleInput($text, $dimensions),
        ]);
        return ApiResponse::embeddings($response, 1)[0];
    }

    public function batchEmbed(array $payload): array
    {
        foreach (batch_slots($payload, null) as $group) {
            $buckets = [];
            foreach ($group['texts'] as $index => $text) {
                [$modality, $input] = $this->prepareInput($text);
                $buckets[$modality][$group['keys'][$index]] = $input;
            }

            foreach ($buckets as $modality => $inputs) {
                $limit = $modality === 'image' ? 32 : $this->maxBatchSize();
                foreach (array_chunk($inputs, $limit, true) as $chunk) {
                    $response = $this->client->post('embeddings', [
                        RequestOptions::JSON => [
                            'model' => $this->model,
                            'input' => array_values($chunk),
                            'modality' => $modality,
                            ...($group['dims'] === null ? [] : ['dimensions' => $group['dims']]),
                        ],
                    ]);
                    $keys = array_keys($chunk);
                    foreach (ApiResponse::embeddings($response, count($keys)) as $index => $vector) {
                        $payload[$keys[$index]]['vector'] = $vector;
                    }
                }
            }
        }

        return $payload;
    }

    public function promiseEmbed(string $text, int $dimensions): PromiseInterface
    {
        return $this->client->postAsync('embeddings', [
            RequestOptions::JSON => $this->singleInput($text, $dimensions),
        ]);
    }

    private function singleInput(string $text, int $dimensions): array
    {
        [$modality, $input] = $this->prepareInput($text);

        return ['model' => $this->model, 'input' => $input, 'modality' => $modality, 'dimensions' => $dimensions];
    }

    private function prepareInput(string $text): array
    {
        if (ImageHelper::isUrl($text)) {
            return ['image', $text];
        }

        if (ImageHelper::isFilePath($text)) {
            $text = ImageHelper::toBase64(ImageHelper::fetchImageContent($text));
        }

        $uri = ImageHelper::base64ImageUri($text);
        if ($uri !== null) {
            return ['image', $uri];
        }

        return ['text', $text];
    }

    public function model(): string
    {
        return $this->model;
    }

    public function maxBatchSize(): int
    {
        return 512;
    }
}
