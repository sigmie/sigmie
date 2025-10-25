<?php

declare(strict_types=1);

namespace Sigmie\Semantic\Providers;

use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Mappings\Types\Text;
use Sigmie\Plugins\Elastiknn\DenseFloatVector;
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
