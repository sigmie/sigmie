<?php

declare(strict_types=1);

namespace Sigmie\Semantic\Embeddings;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Sigmie\Http\JSONClient;
use Sigmie\Http\JSONRequest;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Query\Queries\Elastiknn\NearestNeighbors;
use Sigmie\Semantic\Contracts\Provider;
use Sigmie\Sigmie;

class SigmieAI implements Provider
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
        return new DenseVector($name, dims: 384);
    }

    public function queries(
        string $name,
        array|string $text,
        Type $type
    ): array {

        return [
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
