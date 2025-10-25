<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;

use Sigmie\Base\APIs\Count;
use Sigmie\Base\APIs\Doc;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\APIs\Mget;
use Sigmie\Base\APIs\Search;
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
        $query = [
            'query' => [
                'bool' => [
                    'filter' => $values,
                ],
            ],
        ];

        $res = $this->searchAPICall($index, $query);

        $total = $res->json()['hits']['total']['value'];

        $this->assertTrue($total > 0);
    }

    public function assertIndexMissing(string $index, array $values): void
    {
        $query = [
            'query' => [
                'bool' => [
                    'filter' => $values,
                ],
            ],
        ];

        $res = $this->searchAPICall($index, $query);

        $total = $res->json()['hits']['total']['value'];

        $this->assertTrue($total === 0);
    }

    public function assertDocumentExists(string $index, ElasticsearchDocument $document): void
    {
        $res = $this->docAPICall($index, $document->_id, 'HEAD');

        $this->assertTrue($res->code() === 200);
    }

    public function assertDocumentIsMissing(string $index, ElasticsearchDocument $document): void
    {
        $res = $this->docAPICall($index, $document->_id, 'HEAD');

        $this->assertTrue($res->code() !== 200);
    }
}
