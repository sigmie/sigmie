<?php

declare(strict_types=1);

namespace Sigmie\Semantic\Embeddings;

use GuzzleHttp\Psr7\Uri;
use Sigmie\Http\JSONClient;
use Sigmie\Http\JSONRequest;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Query\Queries\NearestNeighbors;
use Sigmie\Semantic\Contracts\Provider;

class Openai implements Provider
{
    protected JSONClient $http;

    protected int $dims;

    public function __construct(
        protected string $key,
        protected string $model = 'text-embedding-3-small',
        ?int $dims = null
    ) {
        $this->http = JSONClient::createWithToken(['https://api.openai.com/v1'], $this->key);

        $this->dims = $dims ?? match ($this->model) {
            'text-embedding-3-small' => 1536,
            'text-embedding-3-large' => 3072,
            default => throw new \Exception('Invalid model'),
        };
    }

    public function queries(
        string $name,
        string $text,
        Type $type
    ): array {
        return [
            new NearestNeighbors(
                $name,
                $this->embeddings($text)
            )
        ];
    }

    public function embeddings(string $text): array
    {
        $request = new JSONRequest('POST', new Uri('/embeddings'), [
            'input' => $text,
            'model' => $this->model,
            'dimensions' => $this->dims,
        ]);

        $response = $this->http->request($request);

        return $response->json();
    }

    public function type(string $name): Type
    {
        return new DenseVector($name, dims: $this->dims);
    }

    public function threshold(): float
    {
        return 1.3;
    }
}
