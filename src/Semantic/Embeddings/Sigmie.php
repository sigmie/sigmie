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

class Sigmie implements Provider
{
    protected JSONClient $http;

    public function __construct()
    {
        $this->http = JSONClient::create(['https://app.sigmie.com']);
    }

    public function embeddings(string $text): array
    {
        $response = $this->http->request(new JSONRequest('POST', new Uri('/embeddings'), [
            'text' => $text
        ]));

        return $response->json();
    }

    public function type(string $name): Type
    {
        return new DenseVector($name, dims: 384);
    }

    public function queries(
        string $name,
        string $text,
        Type $originalField
    ): array {
        return [
            new NearestNeighbors(
                $name,
                $this->embeddings($text)
            )
        ];
    }

    public function threshold(): float
    {
        return 1.3;
    }
}
