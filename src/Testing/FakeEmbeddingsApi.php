<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use Sigmie\AI\APIs\ApiResponse;
use Sigmie\AI\APIs\CohereEmbeddingsApi;
use Sigmie\AI\APIs\JinaClipApi;
use Sigmie\AI\APIs\OpenAIEmbeddingsApi;
use Sigmie\AI\APIs\VoyageEmbeddingsApi;
use Sigmie\AI\Contracts\EmbeddingsApi;

use function Sigmie\Functions\batch_dimensions;

class FakeEmbeddingsApi implements EmbeddingsApi
{
    use TokenizesText;

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
        return ApiResponse::resizeEmbedding($this->rawEmbed($text, $dimensions), $dimensions);
    }

    protected function rawEmbed(string $text, int $dimensions): array
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
        $vector = $this->rawEmbed($text, $dimensions);
        $body = $this->realApi instanceof CohereEmbeddingsApi
            ? ['embeddings' => ['float' => [$vector]]]
            : ['data' => [['index' => 0, 'embedding' => $vector]]];
        $promise->resolve(new Response(200, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR)));

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
        foreach ($this->batchEmbedCalls as $batch) {
            foreach ($batch as $item) {
                if (($item['text'] ?? '') === $expectedText) {
                    Assert::assertTrue(true);

                    return;
                }
            }
        }

        Assert::fail(sprintf("batchEmbed() was never called with text '%s'", $expectedText));
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
        $defaultDimensions = $this->defaultDimensions();

        foreach ($payload as $key => $item) {
            $dims = batch_dimensions($item, $defaultDimensions) ?? $defaultDimensions;
            $vector = $this->vector((string) ($item['text'] ?? ''), $dims);
            $payload[$key]['vector'] = ApiResponse::resizeEmbedding($vector, $dims);
        }

        return $payload;
    }

    protected function defaultDimensions(): int
    {
        if ($this->realApi instanceof OpenAIEmbeddingsApi) {
            return $this->realApi->defaultDimensions();
        }

        if ($this->realApi instanceof VoyageEmbeddingsApi) {
            return match ($this->model()) {
                'voyage-3-lite' => 512,
                'voyage-large-2', 'voyage-code-2' => 1536,
                default => 1024,
            };
        }

        if ($this->realApi instanceof CohereEmbeddingsApi) {
            return match ($this->model()) {
                'embed-v5.0-fast', 'embed-v5.0-pro' => 2048,
                'embed-v4.0' => 1536,
                'embed-english-light-v3.0', 'embed-multilingual-light-v3.0' => 384,
                'embed-english-v2.0' => 4096,
                'embed-multilingual-v2.0' => 768,
                default => 1024,
            };
        }

        return $this->realApi instanceof JinaClipApi ? 512 : 384;
    }

    protected function vector(string $text, int $dimensions): array
    {
        if ($this->realApi instanceof OpenAIEmbeddingsApi) {
            $this->realApi->requestDimensions($dimensions);
        }

        if ($this->realApi instanceof JinaClipApi
            || ($this->realApi instanceof CohereEmbeddingsApi && ! in_array($dimensions, $this->realApi->supportedDimensions(), true))
            || ($this->realApi instanceof VoyageEmbeddingsApi && ! $this->realApi->supportsDimensions($dimensions))) {
            $dimensions = $this->defaultDimensions();
        }

        $dimensions = max(1, $dimensions);
        $vector = array_fill(0, $dimensions, 0.001);
        $tokens = $this->tokens($text);

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
