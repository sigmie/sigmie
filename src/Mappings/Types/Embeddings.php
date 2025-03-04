<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Semantic\Contracts\Provider;

class Embeddings extends Object_
{
    public function __construct(
        array $fields
    ) {

        $props = new Properties('embeddings', $fields);

        parent::__construct('embeddings', $props);
    }
}
