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

    /**
     * Override embed to use real API
     */
    public function embed(string $text, int $dimensions): array
    {
        // Track if this is an image or text
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

        // Call the real API
        return $this->realApi->embed($text, $dimensions);
    }

    /**
     * Override batchEmbed to use real API
     */
    public function batchEmbed(array $payload): array
    {
        // Analyze the batch to determine types
        $imageCount = 0;
        $textCount = 0;

        foreach ($payload as $index => $item) {
            $text = $item['text'] ?? '';
            if ($this->isImageSource($text)) {
                $imageCount++;
            } else {
                $textCount++;
            }
        }

        $this->mixedBatchCalls[] = [
            'total' => count($payload),
            'images' => $imageCount,
            'texts' => $textCount,
            'items' => $payload,
        ];

        // Track in parent for general batch embed tracking
        $this->batchEmbedCalls[] = $payload;

        // Call the real API
        return $this->realApi->batchEmbed($payload);
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

        Assert::assertEquals($times, $actualCount, "embedImage() was called {$actualCount} times, expected {$times} times");
    }

    /**
     * Assert that text embedding was called
     */
    public function assertTextEmbedWasCalled(int $times = null): void
    {
        $actualCount = count($this->textEmbedCalls);

        if ($times === null) {
            Assert::assertGreaterThan(0, $actualCount, 'embedText() was never called');
            return;
        }

        Assert::assertEquals($times, $actualCount, "embedText() was called {$actualCount} times, expected {$times} times");
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
        if (!$found) {
            foreach ($this->textEmbedCalls as $call) {
                if (($call['text'] ?? '') === $expectedText) {
                    $found = true;
                    break;
                }
            }
        }

        Assert::assertTrue($found, "Text '{$expectedText}' was never embedded");
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

        Assert::fail("No batch was called with {$images} images and {$texts} texts");
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

        Assert::fail("Image from source '{$source}' was never embedded");
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

    /**
     * Check if a string is likely an image source
     */
    protected function isImageSource(string $text): bool
    {
        return ImageHelper::isUrl($text) ||
               ImageHelper::isBase64($text) ||
               ImageHelper::isFilePath($text);
    }
}
