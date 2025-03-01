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

class Noop implements Provider
{
    public function embed(string $text): array
    {
        return [];
    }

    public function type(string $name): Type
    {
        return new DenseVector($name, dims: 0);
    }

    public function queries(
        string $name,
        array|string $text,
        Type $type
    ): array {
        return [];
    }

    public function threshold(): float
    {
        return 0.0;
    }
}
