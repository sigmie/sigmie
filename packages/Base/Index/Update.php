<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\APIs\Calls\Settings as SettingsAPI;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Base\Index\Settings as IndexSettings;
use Sigmie\Support\Collection;

class Update
{
    use SettingsAPI, AliasActions;

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
