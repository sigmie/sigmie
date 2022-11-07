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
    use Mappings;
    use Filters;
    use Tokenizer;
    use CharFilters;
    use Shards;
    use Replicas;

    public function alias(string $alias): static
    {
        $this->alias = $alias;

        return $this;
    }
}
