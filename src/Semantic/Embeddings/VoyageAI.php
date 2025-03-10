<?php

declare(strict_types=1);

namespace Sigmie\Semantic\Embeddings;

use GuzzleHttp\Psr7\Uri;
use RuntimeException;
use Sigmie\Http\JSONClient;
use Sigmie\Http\JSONRequest;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Plugins\Elastiknn\DenseFloatVector;
use Sigmie\Plugins\Elastiknn\NearestNeighbors as ElastiknnNearestNeighbors;
use Sigmie\Query\Queries\NearestNeighbors;
use Sigmie\Semantic\Contracts\AIProvider;
use Sigmie\Sigmie;

class VoyageAI implements AIProvider
{
    protected JSONClient $http;
    protected string $apiKey;
    protected string $model;
    protected int $dimensions;
    protected string $rerankerModel;

    public function __construct(
        string $apiKey, 
        string $model = 'voyage-3', 
        int $dimensions = 1024, 
        string $rerankerModel = 'rerank-2'
    ) {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->dimensions = $dimensions;
        $this->rerankerModel = $rerankerModel;

        $this->http = JSONClient::createWithToken([
            'https://api.voyageai.com',
        ], $this->apiKey);
    }

    public function embed(string $text): array
    {
        $response = $this->http->request(new JSONRequest(
            'POST',
            new Uri('/v1/embeddings'),
            [
                'model' => $this->model,
                'input' => $text,
            ],
        ));

        $data = $response->json();

        if (is_null(dot($data)->get('data'))) {
            throw new RuntimeException(json_encode($data, JSON_PRETTY_PRINT));
        }

        return $data['data'][0]['embedding'] ?? [];
    }

    public function rerank(array $documents, ?string $queryString = null): array
    {
        // If no hits, return empty array
        if (empty($documents)) {
            return [];
        }
                
        // if (empty($query)) {
        //     // If no query is available, can't rerank
        //     return $hits;
        // }
        
        // Call VoyageAI reranking API
        $payload = [
            'model' => $this->rerankerModel,
            'query' => $queryString,
            'documents' => [
                'england',
                'anime',
                'japan',
            ]
        ];
        
        $response = $this->http->request(new JSONRequest(
            'POST',
            new Uri('/v1/rerank'),
            $payload,
        ));
        
        $data = $response->json();

        if (is_null(dot($data)->get('data'))) {
            throw new RuntimeException(json_encode($data, JSON_PRETTY_PRINT));
        }

        // Extract the reranking scores from the API response
        $rerankedScores = [];
        foreach ($data['data'] as $item) {
            $rerankedScores[$item['index']] = $item['relevance_score'];
        }

        // Create a new array with the original hits but with reranked scores
        $rerankedHits = [];
        foreach ($documents as $index => $hit) {
            if (isset($rerankedScores[$index])) {
                // Copy the original hit and update its score
                $rerankedHit = $hit;
                // $rerankedHit['_score'] = $rerankedScores[$index];
                $rerankedHit['_relevance_score'] = $rerankedScores[$index];
                $rerankedHits[] = $rerankedHit;
            } else {
                // If for some reason the hit wasn't reranked, keep it with original score
                $rerankedHits[] = $hit;
            }
        }


        // Sort the hits by the new scores in descending order
        usort($rerankedHits, function ($a, $b) {
            return $b['_relevance_score'] <=> $a['_relevance_score'];
        });

        dd($rerankedHits);
        
        return $rerankedHits;
    }
    

    public function type(string $name): Type
    {
        return Sigmie::isPluginRegistered('elastiknn') ?
            new DenseFloatVector($name, dims: $this->dimensions) :
            new DenseVector($name, dims: $this->dimensions);
    }

    public function queries(
        string $name,
        array|string $text,
        Type $type
    ): array {
        return Sigmie::isPluginRegistered('elastiknn') ? [
            new ElastiknnNearestNeighbors(
                $name,
                $text
            )
        ] : [
            new NearestNeighbors(
                $name,
                $text
            )
        ];
    }

    public function threshold(): float
    {
        return 0.75; // Adjust this threshold based on VoyageAI's recommendations
    }
}
