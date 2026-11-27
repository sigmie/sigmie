<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Sigmie\Index\NewIndex as IndexBuilder;
use Sigmie\Index\Shared\Replicas;
use Sigmie\Index\Shared\Shards;

class UpdateIndex extends IndexBuilder
{
    use Replicas;
    use Shards;

    public function alias(string $alias): static
    {
        $this->alias = $alias;

        return $this;
    }
}
