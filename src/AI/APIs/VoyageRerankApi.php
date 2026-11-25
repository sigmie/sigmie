<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Sigmie\AI\Contracts\RerankApi;

use function Sigmie\Functions\rerank_batches;

class VoyageRerankApi implements RerankApi
{
    protected Client $client;

    public function __construct(
        string $apiKey,
        protected string $model = 'rerank-2.5-lite'
    ) {
        $this->client = new Client([
            'base_uri' => 'https://api.voyageai.com',
            'headers' => [
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 60,
        ]);
    }

    public function rerank(array $newIndexes, string $query, ?int $topK = null): array
    {
        // Allow up to three bytes per original byte after normalization.
        $queryCost = 3 * strlen($query);

        return rerank_batches(
            $newIndexes,
            $topK,
            1000,
            function (array $documents, ?int $limit) use ($query): array {
                $response = $this->client->post('/v1/rerank', [
                    RequestOptions::JSON => [
                        'model' => $this->model,
                        'query' => $query,
                        'documents' => $documents,
                        ...($limit === null ? [] : ['top_k' => $limit]),
                    ],
                ]);

                return ApiResponse::rerank($response, 'data', count($documents));
            },
            static fn (string $document): int => $queryCost + 3 * strlen($document) + 10,
            in_array($this->model, ['rerank-1', 'rerank-lite-1'], true) ? 300_000 : 600_000,
        );
    }
}
