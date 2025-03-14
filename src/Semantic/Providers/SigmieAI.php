<?php

declare(strict_types=1);

namespace Sigmie\Semantic\Providers;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Http\JSONClient;
use Sigmie\Http\JSONRequest;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Mappings\Types\Text;
use Sigmie\Plugins\Elastiknn\DenseFloatVector;
use Sigmie\Plugins\Elastiknn\NearestNeighbors as ElastiknnNearestNeighbors;
use Sigmie\Query\Queries\NearestNeighbors;
use Sigmie\Sigmie;

class SigmieAI extends AbstractAIProvider
{
    protected JSONClient $http;

    public function __construct()
    {
        $this->http = JSONClient::create([
            'https://ai-a.sigmie.app',
            'https://ai-b.sigmie.app',
            'https://ai-c.sigmie.app',
        ]);
    }

    public function rerank(array $documents, string $queryString): array
    {
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

        return $response->json('reranked_scores');
    }

    public function batchEmbed(array $textTypes): array
    {
        if (count($textTypes) === 0) {
            return [];
        }

        $payload = [];
        $textTypes = array_values($textTypes);

        foreach ($textTypes as $textType) {
            $payload[] = [
                'text' => $textType['text'],
                'dims' => (string) $textType['type']->dims()
            ];
        }

        $response = $this->http->request(new JSONRequest(
            'POST',
            new Uri('/embeddings'),
            $payload
        ));

        $embeddings = [];

        foreach ($response->json() as $index => $result) {
            $embeddings[] = [
                'embeddings' => dot($result)->get('embeddings'),
            ];
        }

        return $embeddings;
    }

    public function embed(string $text, Text $originalType): array
    {
        $response = $this->http->request(new JSONRequest(
            'POST',
            new Uri('/embeddings'),
            [
                [
                    'text' => $text,
                    'dims' => (string) $originalType->dims()
                ]
            ]
        ));

        return $response->json('0.embeddings');
    }

    public function type(Text $originalType): Type
    {
        return Sigmie::isPluginRegistered('elastiknn') ?
            new DenseFloatVector(
                name: $originalType->originalName(),
                dims: $originalType->dims()
            ) :
            new DenseVector(
                name: $originalType->originalName(),
                dims: $originalType->dims()
            );
    }

    public function queries(
        array|string $text,
        Text $type
    ): array {

        return Sigmie::isPluginRegistered('elastiknn') ? [
            new ElastiknnNearestNeighbors(
                $type->embeddingsName(),
                $text
            )
        ] : [
            new NearestNeighbors(
                $type->embeddingsName(),
                $text
            )
        ];
    }
}
