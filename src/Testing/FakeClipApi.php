<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use PHPUnit\Framework\Assert;
use Sigmie\Helpers\ImageHelper;

class FakeClipApi extends FakeEmbeddingsApi
{
    protected array $imageEmbedCalls = [];

    protected array $textEmbedCalls = [];

    protected array $mixedBatchCalls = [];

    public function embed(string $text, int $dimensions): array
    {
        if ($this->isImageSource($text)) {
            $this->imageEmbedCalls[] = [
                'source' => $text,
                'dimensions' => $dimensions,
            ];
        } else {
            $this->textEmbedCalls[] = [
                'text' => $text,
                'dimensions' => $dimensions,
            ];
        }

        return $this->vector($this->embeddingText($text), $dimensions);
    }

    public function batchEmbed(array $payload): array
    {
        $imageCount = 0;
        $textCount = 0;

        foreach ($payload as $item) {
            $text = $item['text'] ?? '';
            $dims = (int) ($item['dims'] ?? 0);

            if ($this->isImageSource($text)) {
                $imageCount++;
                $this->imageEmbedCalls[] = [
                    'source' => $text,
                    'dimensions' => $dims,
                ];

                continue;
            }

            $textCount++;
            $this->textEmbedCalls[] = [
                'text' => $text,
                'dimensions' => $dims,
            ];
        }

        $this->mixedBatchCalls[] = [
            'total' => count($payload),
            'images' => $imageCount,
            'texts' => $textCount,
            'items' => $payload,
        ];

        // Track in parent for general batch embed tracking
        $this->batchEmbedCalls[] = $payload;

        return array_map(fn (array $item): array => [
            ...$item,
            'vector' => $this->vector(
                $this->embeddingText((string) ($item['text'] ?? '')),
                (int) ($item['dims'] ?? $item['dimensions'] ?? 512),
            ),
        ], $payload);
    }

    /**
     * Assert that image embedding was called
     */
    public function assertImageEmbedWasCalled(?int $times = null): void
    {
        $actualCount = count($this->imageEmbedCalls);

        if ($times === null) {
            Assert::assertGreaterThan(0, $actualCount, 'embedImage() was never called');

            return;
        }

        Assert::assertEquals($times, $actualCount, sprintf('embedImage() was called %d times, expected %d times', $actualCount, $times));
    }

    /**
     * Assert that text embedding was called
     */
    public function assertTextEmbedWasCalled(?int $times = null): void
    {
        $actualCount = count($this->textEmbedCalls);

        if ($times === null) {
            Assert::assertGreaterThan(0, $actualCount, 'embedText() was never called');

            return;
        }

        Assert::assertEquals($times, $actualCount, sprintf('embedText() was called %d times, expected %d times', $actualCount, $times));
    }

    /**
     * Assert that batch embed was called with specific text
     */
    public function assertBatchEmbedWasCalledWith(string $expectedText): void
    {
        $found = false;

        // Check in batch embed calls
        foreach ($this->batchEmbedCalls as $batch) {
            foreach ($batch as $item) {
                if (($item['text'] ?? '') === $expectedText) {
                    $found = true;
                    break 2;
                }
            }
        }

        // Also check in text embed calls (single embeds)
        if (! $found) {
            foreach ($this->textEmbedCalls as $call) {
                if (($call['text'] ?? '') === $expectedText) {
                    $found = true;
                    break;
                }
            }
        }

        Assert::assertTrue($found, sprintf("Text '%s' was never embedded", $expectedText));
    }

    /**
     * Assert that a batch contained a specific mix of images and texts
     */
    public function assertBatchContainedMix(int $images, int $texts): void
    {
        foreach ($this->mixedBatchCalls as $call) {
            if ($call['images'] === $images && $call['texts'] === $texts) {
                Assert::assertTrue(true);

                return;
            }
        }

        Assert::fail(sprintf('No batch was called with %d images and %d texts', $images, $texts));
    }

    /**
     * Assert that an image from a specific source was embedded
     */
    public function assertImageSourceWasEmbedded(string $source): void
    {
        foreach ($this->imageEmbedCalls as $call) {
            if ($call['source'] === $source) {
                Assert::assertTrue(true);

                return;
            }
        }

        // Also check in batch calls
        foreach ($this->mixedBatchCalls as $batch) {
            foreach ($batch['items'] as $item) {
                if (($item['text'] ?? '') === $source && $this->isImageSource($source)) {
                    Assert::assertTrue(true);

                    return;
                }
            }
        }

        Assert::fail(sprintf("Image from source '%s' was never embedded", $source));
    }

    /**
     * Get image embed calls
     */
    public function getImageEmbedCalls(): array
    {
        return $this->imageEmbedCalls;
    }

    /**
     * Get text embed calls
     */
    public function getTextEmbedCalls(): array
    {
        return $this->textEmbedCalls;
    }

    /**
     * Get mixed batch calls
     */
    public function getMixedBatchCalls(): array
    {
        return $this->mixedBatchCalls;
    }

    /**
     * Reset all tracking
     */
    public function reset(): void
    {
        parent::reset();
        $this->imageEmbedCalls = [];
        $this->textEmbedCalls = [];
        $this->mixedBatchCalls = [];
    }

    protected function embeddingText(string $source): string
    {
        if (! $this->isImageSource($source)) {
            return $source;
        }

        if (str_starts_with($source, 'data:image')) {
            return $this->dataImageText($source);
        }

        return str_replace(['-', '_', '.'], ' ', basename(parse_url($source, PHP_URL_PATH) ?: $source));
    }

    protected function dataImageText(string $source): string
    {
        $data = (string) preg_replace('/^data:image\/[a-z]+;base64,/', '', $source);
        $hash = substr(hash('sha256', base64_decode($data, strict: true) ?: ''), 0, 16);

        return match ($hash) {
            'e8efb9cea15eb819' => 'basketball orange basket',
            '15f8738724be8793' => 'tennis green',
            default => 'data image',
        };
    }

    /**
     * Check if a string is likely an image source
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
