<?php

declare(strict_types=1);

namespace Sigmie\Support\Index;

use Exception;
use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Base\APIs\Index as IndexAPI;
use Sigmie\Base\APIs\Reindex;
use Sigmie\Base\Contracts\Mappings as MappingsInterface;
use Sigmie\Base\Index\Index;
use Sigmie\Base\Mappings\Properties;
use function Sigmie\Helpers\index_name;
use Sigmie\Support\Update\Update;
use Sigmie\Base\Index\Settings;
use Sigmie\Base\Index\Mappings;
use Sigmie\Support\Update\UpdateProxy;

class AliasedIndex extends Index
{
    use Reindex, IndexAPI;

    public function __construct(
        string $identifier,
        protected string $alias,
        array $aliases,
        ?Settings $settings = null,
        ?MappingsInterface $mappings = null,
    ) {
        parent::__construct($identifier, $aliases, $settings, $mappings);
    }

    public function update(callable $update): AliasedIndex
    {
        $update = (new UpdateProxy($this->settings->analysis()))($update);

        $this->settings->analysis()->updateAnalyzers($update->analyzers());

        $charFilters = $update->charFilters();

        $this->defaultAnalyzer()->addCharFilters($charFilters);

        // $this->defaultAnalyzer()->tokenizer();

        // $oldTokenizers = $this->settings->analysis()->tokenizers()->toArray();
        // $newTokenizer = $update->tokenizerValue();
        // $tokenizers = array_merge($oldTokenizers, [$newTokenizer->name() => $newTokenizer]);

        // $this->settings->analysis()->updateTokenizers($tokenizers);

        $this->defaultAnalyzer()->updateTokenizer($update->tokenizerValue());

        $newProps = $update->mappings()->properties()->toArray();
        $oldProps = $this->getMappings()->properties()->toArray();

        $props = array_merge($oldProps, $newProps);

        $newFilters = $update->filters();

        $this->settings->analysis()->updateFilters($newFilters);

        $this->mappings = new Mappings(
            $this->settings->analysis()->defaultAnalyzer(),
            new Properties($props)
        );

        $newName = index_name($this->alias);
        $oldName = $this->identifier;

        $this->settings->primaryShards = $update->numberOfShards();

        $this->settings->replicaShards = 0;
        $this->settings->config('refresh_interval', '-1');

        $this->disableWrite();

        $this->identifier = $newName;
        $this->createIndex($this);

        $this->reindexAPICall($oldName, $newName);

        $this->indexAPICall("/{$newName}/_settings", 'PUT', [
            'number_of_replicas' => $update->numberOfReplicas(),
            'refresh_interval' => null
        ]);

        $this->switchAlias($this->alias, $oldName, $newName);

        $this->deleteIndex($oldName);

        return $this->getIndex($this->alias);
    }

    public function disableWrite(): void
    {
        $this->indexAPICall("/{$this->name()}/_settings", 'PUT', [
            'index' => ['blocks.write' => true]
        ]);
    }

    public function enableWrite(): void
    {
        $this->indexAPICall("/{$this->name()}/_settings", 'PUT', [
            'index' => ['blocks.write' => false]
        ]);
    }

    protected function defaultAnalyzer(): Analyzer
    {
        return $this->settings->analysis()->defaultAnalyzer();
    }
}
