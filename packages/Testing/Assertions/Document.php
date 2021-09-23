<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;

use Sigmie\Base\APIs\Count;
use Sigmie\Base\APIs\Search;
use Sigmie\Base\Documents\Document as ElasticsearchDocument;

trait Document
{
    use Search, Count;

    private string $name;

    public function assertIndexCount(string $index, int $count): void
    {
        $res = $this->countAPICall($index)->json();

        $this->assertEquals($count, $res['count']);
    }

    public function assertIndexHas(string $index, array $values): void
    {
        $query = [
            'query' => [
                'bool' => [
                    'filter' => [
                        // 'match' => [
                        // 'bear' => [
                        //     'query' => 'went',
                        //     'operator' => 'and',
                        //     'zero_terms_query' => 'none'
                        // ]
                        // ]
                    ]
                ]
            ]
        ];

        foreach ($values as $key => $value) {
            $query['query']['bool']['filter'][] =
                [
                    'match' => [
                        $key => [
                            'query' => $value,
                            'operator' => 'and',
                            'zero_terms_query' => 'none'
                        ]
                    ]
                ];
        }

        $res = $this->searchAPICall($index, $query);

        $total = $res->json()['hits']['total']['value'];

        $this->assertTrue($total > 0);
    }

    public function assertIndexMissing(string $index, array $values): void
    {
        $query = [
            'query' => [
                'bool' => [
                    'filter' => [
                        // 'match' => [
                        // 'bear' => [
                        //     'query' => 'went',
                        //     'operator' => 'and',
                        //     'zero_terms_query' => 'none'
                        // ]
                        // ]
                    ]
                ]
            ]
        ];

        foreach ($values as $key => $value) {
            $query['query']['bool']['filter'][] =
                [
                    'match' => [
                        $key => [
                            'query' => $value,
                            'operator' => 'and',
                            'zero_terms_query' => 'none'
                        ]
                    ]
                ];
        }

        $res = $this->searchAPICall($index, $query);

        $total = $res->json()['hits']['total']['value'];

        $this->assertTrue($total > 0);
    }

    public function assertDeleted(ElasticsearchDocument $document): void
    {
        return;
    }

    public function assertDocumentExists(ElasticsearchDocument $document): void
    {
        return;
    }

    public function assertDocumentIsMissing(ElasticsearchDocument $document): void
    {
        return;
    }
}
