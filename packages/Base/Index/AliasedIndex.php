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
use Sigmie\Support\Update\Update;

use function Sigmie\Helpers\index_name;
use function Sigmie\Helpers\name_configs;

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
        /** @var  Update $update */
        $update = $update(new Update);

        $oldDocsCount = count($this);

        $newName = index_name($this->alias);
        $oldName = $this->identifier;
        $this->identifier = $newName;

        $updateArray = $update->toRaw();

        $this->settings->primaryShards = $updateArray['settings']['number_of_shards'];
        $this->settings->replicaShards = $updateArray['settings']['number_of_replicas'];

        $index = $this->createIndex($this);

        // $this->reindexAPICall($oldName, $newName);

        // $newDocsCount = count($index);

        // if ($newDocsCount !== $oldDocsCount) {
        //     throw new Exception('Docs count missmatch');
        // }

        $this->switchAlias($this->alias, $oldName, $newName);

        return $this->getIndex($this->alias);
    }

    protected function defaultAnalyzer(): Analyzer
    {
        return $this->settings->analysis->defaultAnalyzer();
    }
}
