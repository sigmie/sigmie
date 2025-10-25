<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\RequestOptions;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\Helpers\ImageHelper;

class InfinityClipApi implements EmbeddingsApi
{
    protected Client $client;

    public function __construct(
        string $baseUrl = 'http://localhost:7996',
        protected string $model = 'wkcn/TinyCLIP-ViT-8M-16-Text-3M-YFCC15M'
    ) {
        $this->client = new Client([
            'base_uri' => $baseUrl,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 120, // Longer timeout for image processing
        ]);
    }

    /**
     * Generate embeddings for a single text or image
     * If the text looks like an image source, it will be processed as an image
     */
    public function embed(string $text, int $dimensions): array
    {
        // Check if this is an image source
        if ($this->isImageSource($text)) {
            return $this->embedImage($text, $dimensions);
        }

        // Otherwise, embed as text
        return $this->embedText($text, $dimensions);
    }

    /**
     * Generate embeddings for multiple texts/images in batch
     *
     * NOTE: Infinity TinyCLIP only supports HTTP/HTTPS URLs for images.
     * Local file paths and data URIs are NOT supported and will cause timeouts.
     */
    public function batchEmbed(array $payload): array
    {
        if ($payload === []) {
            return [];
        }

        $inputs = [];
        $hasImage = false;
        $hasText = false;

        // Process each item and determine if it's text or image
        foreach ($payload as $index => $item) {
            $text = $item['text'] ?? '';
            $inputs[] = $text;  // Send as-is (must be URLs for images)

            if ($this->isImageSource($text)) {
                $hasImage = true;
            } else {
                $hasText = true;
            }
        }

        // Determine modality - if mixed or all images, use image modality
        $modality = $hasImage ? 'image' : 'text';

        // Send to API with modality parameter
        $response = $this->client->post('/embeddings', [
            RequestOptions::JSON => [
                'model' => $this->model,
                'input' => $inputs,
                'modality' => $modality,
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        // Map embeddings back to payload
        foreach ($data['data'] as $index => $result) {
            $payload[$index]['vector'] = $result['embedding'];
        }

        return $payload;
    }

    /**
     * Generate embeddings asynchronously
     */
    public function promiseEmbed(string $text, int $dimensions): Promise
    {
        $modality = $this->isImageSource($text) ? 'image' : 'text';

        return $this->client->postAsync('/embeddings', [
            RequestOptions::JSON => [
                'model' => $this->model,
                'input' => $text,
                'modality' => $modality,
            ]
        ]);
    }

    public function model(): string
    {
        return $this->model;
    }

    /**
     * Embed text using CLIP text encoder
     */
    protected function embedText(string $text, int $dimensions): array
    {
        $response = $this->client->post('/embeddings', [
            RequestOptions::JSON => [
                'model' => $this->model,
                'input' => $text,
                'modality' => 'text',
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return $data['data'][0]['embedding'];
    }

    /**
     * Embed image using CLIP image encoder
     *
     * NOTE: Only HTTP/HTTPS URLs are supported. Local file paths and data URIs
     * are NOT supported by Infinity TinyCLIP and will cause timeouts.
     */
    protected function embedImage(string $imageSource, int $dimensions): array
    {
        // Send URL directly with image modality
        $response = $this->client->post('/embeddings', [
            RequestOptions::JSON => [
                'model' => $this->model,
                'input' => $imageSource,
                'modality' => 'image',
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return $data['data'][0]['embedding'];
    }

    /**
     * Check if a string is likely an image source (URL, base64, or file path)
     */
    protected function isImageSource(string $text): bool
    {
        if (ImageHelper::isUrl($text)) {
            return true;
        }
        if (ImageHelper::isBase64($text)) {
            return true;
        }
        return ImageHelper::isFilePath($text);
    }
}
