<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use GuzzleHttp\Promise\Promise;
use PHPUnit\Framework\Assert;
use Sigmie\AI\Contracts\EmbeddingsApi;

class FakeEmbeddingsApi implements EmbeddingsApi
{
    protected array $embedCalls = [];

    protected array $batchEmbedCalls = [];

    public function __construct(
        protected EmbeddingsApi $realApi
    ) {}

    public function embed(string $text, int $dimensions): array
    {
        $this->embedCalls[] = [
            'text' => $text,
            'dimensions' => $dimensions,
        ];

        return $this->realApi->embed($text, $dimensions);
    }

    public function batchEmbed(array $payload): array
    {
        $this->batchEmbedCalls[] = $payload;

        return $this->realApi->batchEmbed($payload);
    }

    public function promiseEmbed(string $text, int $dimensions): Promise
    {
        return $this->realApi->promiseEmbed($text, $dimensions);
    }

    public function model(): string
    {
        return $this->realApi->model();
    }

    public function assertEmbedWasCalled(?int $times = null): void
    {
        $actualCount = count($this->embedCalls);

        if ($times === null) {
            Assert::assertGreaterThan(0, $actualCount, 'embed() was never called');

            return;
        }

        Assert::assertEquals($times, $actualCount, sprintf('embed() was called %d times, expected %d times', $actualCount, $times));
    }

    public function assertEmbedWasCalledWith(string $text, ?int $dimensions = null): void
    {
        foreach ($this->embedCalls as $call) {
            if ($call['text'] === $text && ($dimensions === null || $call['dimensions'] === $dimensions)) {
                Assert::assertTrue(true);

                return;
            }
        }

        $message = $dimensions === null
            ? sprintf('embed() was never called with text: "%s"', $text)
            : sprintf('embed() was never called with text: "%s" and dimensions: %d', $text, $dimensions);

        Assert::fail($message);
    }

    public function assertBatchEmbedWasCalled(?int $times = null): void
    {
        $actualCount = count($this->batchEmbedCalls);

        if ($times === null) {
            Assert::assertGreaterThan(0, $actualCount, 'batchEmbed() was never called');

            return;
        }

        Assert::assertEquals($times, $actualCount, sprintf('batchEmbed() was called %d times, expected %d times', $actualCount, $times));
    }

    public function assertBatchEmbedWasCalledWithCount(int $itemCount): void
    {
        foreach ($this->batchEmbedCalls as $call) {
            if (count($call) === $itemCount) {
                Assert::assertTrue(true);

                return;
            }
        }

        Assert::fail(sprintf('batchEmbed() was never called with %d items', $itemCount));
    }

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

        // Also check in single embed calls
        if (! $found) {
            foreach ($this->embedCalls as $call) {
                if (($call['text'] ?? '') === $expectedText) {
                    $found = true;
                    break;
                }
            }
        }

        Assert::assertTrue($found, sprintf("Text '%s' was never embedded", $expectedText));
    }

    public function getEmbedCalls(): array
    {
        return $this->embedCalls;
    }

    public function getBatchEmbedCalls(): array
    {
        return $this->batchEmbedCalls;
    }

    public function reset(): void
    {
        $this->embedCalls = [];
        $this->batchEmbedCalls = [];
    }
}
