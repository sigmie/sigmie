<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use PHPUnit\Framework\Assert;
use Sigmie\AI\Contracts\RerankApi;

class FakeRerankApi implements RerankApi
{
    protected array $rerankCalls = [];

    public function __construct(
        protected RerankApi $realApi
    ) {}

    public function rerank(array $documents, string $query, ?int $topK = null): array
    {
        $this->rerankCalls[] = [
            'documents' => $documents,
            'documents_count' => count($documents),
            'query' => $query,
            'topK' => $topK,
        ];

        return $this->realApi->rerank($documents, $query, $topK);
    }

    public function assertRerankWasCalled(?int $times = null): void
    {
        $actualCount = count($this->rerankCalls);

        if ($times === null) {
            Assert::assertGreaterThan(0, $actualCount, 'rerank() was never called');

            return;
        }

        Assert::assertEquals($times, $actualCount, sprintf('rerank() was called %d times, expected %d times', $actualCount, $times));
    }

    public function assertRerankWasCalledWith(string $query, ?int $topK = null): void
    {
        foreach ($this->rerankCalls as $call) {
            if ($call['query'] === $query && ($topK === null || $call['topK'] === $topK)) {
                Assert::assertTrue(true);

                return;
            }
        }

        $message = $topK === null
            ? sprintf('rerank() was never called with query: "%s"', $query)
            : sprintf('rerank() was never called with query: "%s" and topK: %d', $query, $topK);

        Assert::fail($message);
    }

    public function assertRerankWasCalledWithDocumentCount(int $count): void
    {
        foreach ($this->rerankCalls as $call) {
            if ($call['documents_count'] === $count) {
                Assert::assertTrue(true);

                return;
            }
        }

        Assert::fail(sprintf('rerank() was never called with %d documents', $count));
    }

    public function getRerankCalls(): array
    {
        return $this->rerankCalls;
    }

    public function reset(): void
    {
        $this->rerankCalls = [];
    }
}
