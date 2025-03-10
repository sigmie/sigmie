<?php

declare(strict_types=1);

namespace Sigmie\Semantic\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Sigmie\Http\JSONClient;
use Sigmie\Http\JSONRequest;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Plugins\Elastiknn\DenseFloatVector;
use Sigmie\Plugins\Elastiknn\NearestNeighbors as ElastiknnNearestNeighbors;
use Sigmie\Query\Queries\NearestNeighbors;
use Sigmie\Semantic\Contracts\AIProvider;
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

    public function embed(string $text): array
    {
        $response = $this->http->request(new JSONRequest(
            'POST',
            new Uri('/embeddings'),
            [
                'text' => $text
            ]
        ));

        return $response->json();
    }

    public function type(string $name): Type
    {
        return Sigmie::isPluginRegistered('elastiknn') ?
            new DenseFloatVector($name, dims: 384) :
            new DenseVector($name, dims: 384);
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
        return 1.3;
    }
}
