<?php

declare(strict_types=1);

namespace Sigmie\Base\Index\Update;

use Sigmie\Base\Index\AliasActions;
use Sigmie\Base\Index\Index;

class Plan
{
    use AliasActions;

    protected string $prefix;

    public function __construct(protected Index $index)
    {
        $this->setHttpConnection($index->getHttpConnection());
    }

    public function stopwords(array $stopwords)
    {
        $mappings = $this->index->getMappings();

        $settings = $this->index->getSettings();

        // $filters = new Collection($settings->analysis->filters());

        // $filters = $filters->filter(fn (TokenFilter $tokenFilter) => $tokenFilter instanceof Stopwords === false);
    }
}
