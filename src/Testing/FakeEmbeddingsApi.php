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
        protected EmbeddingsApi $realApi,
        protected ?int $maxBatchSizeOverride = null,
    ) {}

    public function overrideMaxBatchSize(?int $size): static
    {
        $this->maxBatchSizeOverride = $size;

        return $this;
    }

    public function embed(string $text, int $dimensions): array
    {
        $this->embedCalls[] = [
            'text' => $text,
            'dimensions' => $dimensions,
        ];

        return $this->vector($text, $dimensions);
    }

    public function batchEmbed(array $payload): array
    {
        $this->batchEmbedCalls[] = $payload;

        return $this->embedPayload($payload);
    }

    public function promiseEmbed(string $text, int $dimensions): Promise
    {
        $promise = new Promise;
        $promise->resolve($this->embed($text, $dimensions));

        return $promise;
    }

    public function model(): string
    {
        return $this->realApi->model();
    }

    public function maxBatchSize(): int
    {
        return $this->maxBatchSizeOverride ?? $this->realApi->maxBatchSize();
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

    protected function embedPayload(array $payload): array
    {
        return array_map(fn (array $item): array => [
            ...$item,
            'vector' => $this->vector(
                (string) ($item['text'] ?? ''),
                (int) ($item['dims'] ?? $item['dimensions'] ?? 384),
            ),
        ], $payload);
    }

    protected function vector(string $text, int $dimensions): array
    {
        $dimensions = max(1, $dimensions);
        $vector = array_fill(0, $dimensions, 0.001);
        $normalized = $this->normalize($text);
        $tokens = $this->tokens($normalized);

        foreach ($tokens as $token) {
            $vector[crc32($token) % $dimensions] += 0.25;
        }

        foreach ($this->semanticGroups() as $index => $terms) {
            foreach ($terms as $term) {
                if (in_array($term, $tokens, true)) {
                    $vector[$index % $dimensions] += 4.0;
                }
            }
        }

        $magnitude = sqrt(array_sum(array_map(fn (float $value): float => $value * $value, $vector)));

        return array_map(fn (float $value): float => $value / $magnitude, $vector);
    }

    protected function normalize(string $text): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower($text)));
    }

    protected function tokens(string $text): array
    {
        return array_values(array_filter(explode(' ', $text)));
    }

    protected function semanticGroups(): array
    {
        return [
            ['accountant', 'accounting', 'financial', 'finance', 'ledger', 'tax', 'audit', 'bookkeeping', 'gaap', 'quickbooks', 'xero', 'reconciliation'],
            ['sales', 'selling', 'quota', 'revenue', 'pipeline', 'client', 'prospect', 'crm', 'contract', 'saas', 'acquisition', 'retention'],
            ['trainer', 'training', 'coach', 'fitness', 'workshop', 'onboarding', 'instructional', 'learning', 'wellness', 'certification'],
            ['woman', 'lady', 'queen', 'princess', 'female', 'royal', 'crown', 'monarch', 'regal', 'chess'],
            ['king', 'man', 'male'],
            ['web', 'framework', 'php', 'laravel', 'django', 'python', 'development'],
            ['javascript', 'react', 'library', 'interfaces'],
            ['pirate', 'pirates', 'ship', 'sailing', 'ocean', 'sea', 'adventure', 'treasure', 'caribbean'],
            ['car', 'vehicle', 'automobile', 'racing', 'ferrari', 'speed', 'sedan'],
            ['basketball', 'basket', 'orange'],
            ['tennis', 'green'],
            ['beach', 'vacation', 'puzzle'],
            ['motorcycle', 'bike', 'wheels'],
            ['red'],
            ['blue'],
        ];
    }
}
