<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use GuzzleHttp\Promise\Promise;
use Sigmie\AI\Contracts\EmbeddingsApi;
use PHPUnit\Framework\Assert;

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

    public function assertEmbedWasCalled(int $times = null): void
    {
        $actualCount = count($this->embedCalls);

        if ($times === null) {
            Assert::assertGreaterThan(0, $actualCount, 'embed() was never called');
            return;
        }

        Assert::assertEquals($times, $actualCount, "embed() was called {$actualCount} times, expected {$times} times");
    }

    public function assertEmbedWasCalledWith(string $text, int $dimensions = null): void
    {
        foreach ($this->embedCalls as $call) {
            if ($call['text'] === $text && ($dimensions === null || $call['dimensions'] === $dimensions)) {
                Assert::assertTrue(true);
                return;
            }
        }

        $message = $dimensions === null
            ? "embed() was never called with text: \"{$text}\""
            : "embed() was never called with text: \"{$text}\" and dimensions: {$dimensions}";

        Assert::fail($message);
    }

    public function assertBatchEmbedWasCalled(int $times = null): void
    {
        $actualCount = count($this->batchEmbedCalls);

        if ($times === null) {
            Assert::assertGreaterThan(0, $actualCount, 'batchEmbed() was never called');
            return;
        }

        Assert::assertEquals($times, $actualCount, "batchEmbed() was called {$actualCount} times, expected {$times} times");
    }

    public function assertBatchEmbedWasCalledWithCount(int $itemCount): void
    {
        foreach ($this->batchEmbedCalls as $call) {
            if (count($call) === $itemCount) {
                Assert::assertTrue(true);
                return;
            }
        }

        Assert::fail("batchEmbed() was never called with {$itemCount} items");
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
