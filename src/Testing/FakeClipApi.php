<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use PHPUnit\Framework\Assert;
use Sigmie\Helpers\ImageHelper;

use function Sigmie\Functions\batch_dimensions;

class FakeClipApi extends FakeEmbeddingsApi
{
    protected array $imageEmbedCalls = [];

    protected array $textEmbedCalls = [];

    protected array $mixedBatchCalls = [];

    protected function rawEmbed(string $text, int $dimensions): array
    {
        $this->recordInput($text, $dimensions);

        return parent::rawEmbed($text, $dimensions);
    }

    public function batchEmbed(array $payload): array
    {
        $imagesBefore = count($this->imageEmbedCalls);
        foreach ($payload as $item) {
            $this->recordInput((string) ($item['text'] ?? ''), batch_dimensions($item, null) ?? $this->defaultDimensions());
        }

        $images = count($this->imageEmbedCalls) - $imagesBefore;
        $this->mixedBatchCalls[] = [
            'total' => count($payload),
            'images' => $images,
            'texts' => count($payload) - $images,
            'items' => $payload,
        ];

        return parent::batchEmbed($payload);
    }

    private function recordInput(string $text, int $dimensions): void
    {
        if ($this->isImageSource($text)) {
            $this->imageEmbedCalls[] = ['source' => $text, 'dimensions' => $dimensions];

            return;
        }

        $this->textEmbedCalls[] = ['text' => $text, 'dimensions' => $dimensions];
    }

    protected function defaultDimensions(): int
    {
        return 512;
    }

    protected function vector(string $text, int $dimensions): array
    {
        return parent::vector($this->embeddingText($text), $dimensions);
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
        if (ImageHelper::isFilePath($source)) {
            $source = ImageHelper::toBase64(ImageHelper::fetchImageContent($source));
        }

        if (! $this->isImageSource($source)) {
            return $source;
        }

        if (ImageHelper::isBase64($source)) {
            return $this->dataImageText($source);
        }

        $filename = basename(parse_url($source, PHP_URL_PATH) ?: $source);

        return str_replace(['-', '_', '.'], ' ', rawurldecode($filename));
    }

    protected function dataImageText(string $source): string
    {
        $hash = substr(hash('sha256', ImageHelper::fetchImageContent($source)), 0, 16);

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
