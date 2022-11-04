<?php


declare(strict_types=1);

namespace Sigmie\Support\Update;

use Sigmie\Index\Builder as IndexBuilder;
use Sigmie\Support\Shared\CharFilters;
use Sigmie\Support\Shared\Filters;
use Sigmie\Support\Shared\Mappings;
use Sigmie\Support\Shared\Replicas;
use Sigmie\Support\Shared\Shards;
use Sigmie\Support\Shared\Tokenizer;

class Update extends IndexBuilder
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
