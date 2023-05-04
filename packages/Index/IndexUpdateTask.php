<?php

declare(strict_types=1);

namespace Sigmie\Index;

use RuntimeException;
use Sigmie\Base\APIs\Reindex;
use Sigmie\Base\APIs\Tasks;
use Sigmie\Base\Contracts\ElasticsearchConnection;

class IndexUpdateTask
{
    use Tasks;
    use Reindex;
    use Actions;

    protected string $task;

    public function __construct(
        ElasticsearchConnection $elasticsearchConnection,
        protected string $source,
        protected string $dest,
        protected string $oldAlias,
        protected string $newAlias,
        protected int $requestedReplicas
    ) {
        $this->setElasticsearchConnection($elasticsearchConnection);

        $res = $this->reindexAPICall($source, $dest, false);

        $this->task = $res->json('task');
    }

    public function task(): array
    {
        return $this->taskAPICall($this->task)->json();
    }

    protected function runningTasks(): array
    {
        $nodes = $this->taskAPICall()->json('nodes');
        $res = [];

        foreach ($nodes as $node) {
            foreach ($node['tasks'] as $id => $task) {
                $res[] = $id;
            }
        }

        return $res;
    }

    public function isCompleted(): bool
    {
        return !in_array($this->task, $this->runningTasks());
    }

    public function finish()
    {
        $this->indexAPICall("{$this->dest}/_settings", 'PUT', [
            'number_of_replicas' => $this->requestedReplicas,
            'refresh_interval' => '1s',
        ]);

        if ($this->oldAlias === $this->newAlias) {
            $this->switchAlias($this->newAlias, $this->source, $this->dest);
        } else {
            $this->createAlias($this->dest, $this->newAlias);
        }

        $this->deleteIndex($this->source);

        $index = $this->getIndex($this->newAlias);

        if ($index instanceof AliasedIndex) {
            return $index;
        }

        throw new RuntimeException('Something went wrong while updating index.');
    }
}
