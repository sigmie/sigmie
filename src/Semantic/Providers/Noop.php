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
use Sigmie\Mappings\Types\Text;
use Sigmie\Plugins\Elastiknn\DenseFloatVector;
use Sigmie\Query\Queries\NearestNeighbors;
use Sigmie\Semantic\Contracts\AIProvider;
use Sigmie\Sigmie;

class Noop extends AbstractAIProvider
{
    public function embed(string $text, Text $originalType): array
    {
        return Sigmie::isPluginRegistered('elastiknn') ?
            [] :
            [-1];
    }

    public function type(Text $originalType): Type
    {
        return Sigmie::isPluginRegistered('elastiknn') ?
            new DenseFloatVector($originalType->originalName(), dims: 0) :
            new DenseVector($originalType->originalName(), dims: 1);
    }

    public function queries(
        array|string $text,
        Text $type
    ): array {
        return [];
    }
}
