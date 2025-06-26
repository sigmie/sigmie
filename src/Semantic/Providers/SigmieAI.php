<?php

declare(strict_types=1);

namespace Sigmie\Semantic\Providers;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Enums\VectorStrategy;
use Sigmie\Http\JSONClient;
use Sigmie\Http\JSONRequest;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Text;
use Sigmie\Plugins\Elastiknn\DenseFloatVector;
use Sigmie\Plugins\Elastiknn\NearestNeighbors as ElastiknnNearestNeighbors;
use Sigmie\Query\FunctionScore;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\NearestNeighbors;
use Sigmie\Query\Queries\Text\Nested as TextNested;
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

        if (count($documents) === 0) {
            return [];
        }

        $response = $this->http->request(
            new JSONRequest(
                'POST',
                new Uri('/rerank'),
                $payload
            )
        );

        return $response->json('reranked_scores');
    }

    public function batchEmbed(array $payload): array
    {
        if (count($payload) === 0) {
            return [];
        }

        $response = $this->http->request(new JSONRequest(
            'POST',
            new Uri('/embeddings'),
            $payload
        ));

        foreach ($response->json() as $index => $result) {
            // $embeddings[] = [
            //     'embeddings' => dot($result)->get('embeddings'),
            //     dot($result)->get('embeddings')
            // ];

            $payload[$index]['vector'] = dot($result)->get('embeddings');
        }

        return $payload;
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
        throw new \Exception('Should be removed');
        // if ($originalType->strategy() === VectorStrategy::ScriptScore) {

        //     $type =
        //         Sigmie::isPluginRegistered('elastiknn') ?
        //         new DenseFloatVector(
        //             // name: $originalType->originalName(),
        //             name: 'embedding',
        //             dims: $originalType->dims()
        //         ) :
        //         new DenseVector(
        //             // name: $originalType->originalName(),
        //             name: 'embedding',
        //             dims: $originalType->dims()
        //         );

        //     $field = new Nested($originalType->name());

        //     $props = new NewProperties($originalType->name());
        //     $props->type($type);

        //     $field->properties($props);

        //     return $field;
        // }

        // $type =
        //     Sigmie::isPluginRegistered('elastiknn') ?
        //     new DenseFloatVector(
        //         name: $originalType->originalName(),
        //         dims: $originalType->dims()
        //     ) :
        //     new DenseVector(
        //         name: $originalType->originalName(),
        //         dims: $originalType->dims()
        //     );

        // return $type;
    }

    public function queries(
        array|string $text,
        Text $type
    ): array {

        dd($text);

        if ($type->strategy() === VectorStrategy::ScriptScore) {
            $fnQuery = new FunctionScore(
                query: new MatchAll(),
                source: 'cosineSimilarity(params.query_vector, \'embeddings.' . $type->name() . '.embedding\') + 1.0',
                boostMode: 'replace', // Doesn't matter, because of match all 
                params: [
                    'query_vector' => $text
                ]
            );

            $query = new TextNested(
                "embeddings.{$type->name()}",
                $fnQuery,
                scoreMode: $type->vectorMode()
            );

            return [
                $query
            ];
        }

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
