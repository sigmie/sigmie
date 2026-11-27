<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;

use PHPUnit\Framework\Assert;
use Sigmie\Base\APIs\Count;
use Sigmie\Base\APIs\Doc;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\APIs\Mget;
use Sigmie\Base\APIs\Search;
use Sigmie\Document\AliveCollection;
use Sigmie\Document\Document as ElasticsearchDocument;

trait Document
{
    use Count;
    use Doc;
    use Index;
    use Mget;
    use Search;

    private string $name;

    public function assertIndexCount(string $index, int $count): void
    {
        $res = $this->countAPICall($index)->json();

        $this->assertEquals($count, $res['count'], sprintf('Failed to assert that the %s index has %d documents.', $index, $count));
    }

    public function assertIndexHas(string $index, array $values): void
    {
        Assert::assertTrue($this->indexHasMatch($index, $values));
    }

    public function assertIndexMissing(string $index, array $values): void
    {
        Assert::assertFalse($this->indexHasMatch($index, $values));
    }

    private function indexHasMatch(string $index, array $values): bool
    {
        $response = $this->searchAPICall($index, [
            'query' => ['bool' => ['filter' => $values]],
            'size' => 0,
            'track_total_hits' => 1,
        ]);

        return $response->total() > 0;
    }

    public function assertDocumentExists(string $index, ElasticsearchDocument $document): void
    {
        Assert::assertTrue($this->hasDocument($index, $document));
    }

    public function assertDocumentIsMissing(string $index, ElasticsearchDocument $document): void
    {
        Assert::assertFalse($this->hasDocument($index, $document));
    }

    private function hasDocument(string $index, ElasticsearchDocument $document): bool
    {
        Assert::assertTrue(isset($document->_id), 'Document assertions require an assigned document ID.');

        return (new AliveCollection($index, $this->getElasticsearchConnection()))->has($document->_id);
    }
}
