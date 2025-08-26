<?php

declare(strict_types=1);

namespace Sigmie\AI\Rerankers;

use GuzzleHttp\Psr7\Uri;
use Sigmie\AI\Contracts\Reranker;
use Sigmie\Http\JSONClient;
use Sigmie\Http\JSONRequest;

class SigmieReranker implements Reranker
{
    protected JSONClient $http;
    protected string $model = 'sigmie-reranker';

    public function __construct()
    {
        $this->http = JSONClient::create([
            'https://ai-b.sigmie.app',
        ]);
    }

    public function rerank(array $documents, string $queryString, ?int $topK = null): array
    {
        if (count($documents) === 0) {
            return [];
        }

        $payload = [
            'documents' => $documents,
            'query' => $queryString,
        ];

        $response = $this->http->request(
            new JSONRequest(
                'POST',
                new Uri('/rerank'),
                $payload
            )
        );

        $scores = $response->json('reranked_scores');
        
        if ($topK !== null && count($scores) > $topK) {
            return array_slice($scores, 0, $topK);
        }

        return $scores;
    }

    public function getModel(): string
    {
        return $this->model;
    }
}