<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Sigmie\Index\NewIndex as IndexBuilder;
use Sigmie\Index\Shared\CharFilters;
use Sigmie\Index\Shared\Filters;
use Sigmie\Index\Shared\Mappings;
use Sigmie\Index\Shared\Replicas;
use Sigmie\Index\Shared\Shards;
use Sigmie\Index\Shared\Tokenizer;

class UpdateIndex extends IndexBuilder
{
    use CharFilters;
    use Filters;
    use Mappings;
    use Replicas;
    use Shards;
    use Tokenizer;

    public function alias(string $alias): static
    {
        $this->alias = $alias;

        return $this;
    }
}
