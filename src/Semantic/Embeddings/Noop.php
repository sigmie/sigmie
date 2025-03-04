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
use Sigmie\Plugins\Elastiknn\DenseFloatVector;
use Sigmie\Query\Queries\NearestNeighbors;
use Sigmie\Semantic\Contracts\Provider;
use Sigmie\Sigmie;

class Noop implements Provider
{
    public function embed(string $text): array
    {
        return Sigmie::isPluginRegistered('elastiknn') ?
            [] :
            [-1];
    }

    public function type(string $name): Type
    {
        return Sigmie::isPluginRegistered('elastiknn') ?
            new DenseFloatVector($name, dims: 0) :
            new DenseVector($name, dims: 1);
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
