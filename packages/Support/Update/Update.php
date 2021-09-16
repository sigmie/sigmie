<?php


declare(strict_types=1);

namespace Sigmie\Support\Update;

use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Contracts\Analysis;
use Sigmie\Base\Contracts\Mappings as MappingsInterface;
use Sigmie\Base\Contracts\Tokenizer as TokenizerInterface;
use Sigmie\Support\Analysis\AnalyzerUpdate;
use Sigmie\Support\Collection;
use Sigmie\Support\Contracts\Collection as CollectionInterface;
use Sigmie\Support\Shared\CharFilters;
use Sigmie\Support\Shared\Filters;
use Sigmie\Support\Shared\Mappings;
use Sigmie\Support\Shared\Replicas;
use Sigmie\Support\Shared\Shards;
use Sigmie\Support\Shared\Tokenizer;
use Sigmie\Support\Update\TokenizerBuilder as UpdateTokenizerBuilder;
use Sigmie\Base\Index\Builder as IndexBuilder;

class Update extends IndexBuilder
{
    use Mappings, Filters, Tokenizer, CharFilters, Shards, Replicas;

    public function alias(string $alias): static
    {
        $this->alias = $alias;

        return $this;
    }
}
