<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use PHPUnit\Framework\Assert;
use Sigmie\AI\Contracts\RerankApi;

use function Sigmie\Functions\rerank_texts;

class FakeRerankApi implements RerankApi
{
    use TokenizesText;

    protected array $rerankCalls = [];

    public function __construct(
        protected RerankApi $realApi
    ) {}

    public function rerank(array $newIndexes, string $query, ?int $topK = null): array
    {
        $this->rerankCalls[] = [
            'documents' => $newIndexes,
            'documents_count' => count($newIndexes),
            'query' => $query,
            'topK' => $topK,
        ];

        if ($newIndexes === [] || $topK === 0) {
            return [];
        }

        $queryTokens = $this->tokens($query);

        $documents = rerank_texts($newIndexes);
        $scores = array_map(fn (string $document, int $index): array => [
            'index' => $index,
            'score' => $this->score($document, $queryTokens),
        ], $documents, array_keys($documents));

        usort($scores, fn (array $left, array $right): int => $right['score'] <=> $left['score']);

        if ($topK === null) {
            return $scores;
        }

        return array_slice($scores, 0, $topK);
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

    protected function score(string $document, array $queryTokens): float
    {
        $documentTokens = $this->tokens($document);
        $overlap = array_intersect($queryTokens, $documentTokens);

        $length = count($documentTokens);

        return (count($overlap) + $length / (1000 + $length)) / (count($queryTokens) + 1);
    }
}
