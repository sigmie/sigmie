<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Carbon\Carbon;
use Exception;
use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Base\APIs\Calls\Reindex;
use Sigmie\Base\Index\Index;
use Sigmie\Base\Index\Mappings;
use Sigmie\Base\Index\Settings;

class AliasedIndex extends Index
{
    use Reindex;

    public function __construct(
        string $identifier,
        protected string $alias,
        array $aliases,
        ?Settings $settings = null,
        ?Mappings $mappings = null,
    ) {
        parent::__construct($identifier, $aliases, $settings, $mappings);
    }

    public function update(callable $update): Index
    {
        $analyzer = $update($this->defaultAnalyzer());
        $oldDocsCount = count($this);

        $this->settings->analysis->setDefaultAnalyzer($analyzer);

        $timestamp = Carbon::now()->format('YmdHisu');

        //TODO remove v2
        $newIdentifier = "{$this->alias}_{$timestamp}_v2";
        $oldIdentifier = $this->identifier;

        $this->identifier = $newIdentifier;

        ray($this->settings->analysis->defaultAnalyzer())->blue();
        ray($this->settings->analysis->toRaw())->green();

        $index = $this->createIndex($this);

        $this->reindexAPICall($oldIdentifier, $newIdentifier);

        $newDocsCount = count($index);

        if ($newDocsCount !== $oldDocsCount) {
            throw new Exception('Docs count missmatch');
        }

        // $this->switchAlias($this->alias, $oldIdentifier, $newIdentifier);

        $index->alias($this->alias);

        return $index;
    }

    protected function defaultAnalyzer(): Analyzer
    {
        return $this->settings->analysis->defaultAnalyzer();
    }
}
