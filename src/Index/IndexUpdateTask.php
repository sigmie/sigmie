<?php

declare(strict_types=1);

namespace Sigmie\Index;

use RuntimeException;
use Sigmie\Base\APIs\Reindex;
use Sigmie\Base\APIs\Tasks;
use Sigmie\Base\Contracts\ElasticsearchConnection;

class IndexUpdateTask
{
    use Actions;
    use Reindex;
    use Tasks;

    protected string $task;

    public function __construct(
        ElasticsearchConnection $elasticsearchConnection,
        public readonly string $source,
        public readonly string $dest,
        public readonly string $oldAlias,
        public readonly string $newAlias,
        public readonly int $requestedReplicas
    ) {
        $this->setElasticsearchConnection($elasticsearchConnection);

        $res = $this->reindexAPICall($source, $dest, waitForCompletion: false);

        $this->task = $res->json('task');
    }

    public function pack(): array
    {
        return [
            'source' => $this->source,
            'dest' => $this->dest,
            'old_alias' => $this->oldAlias,
            'new_alias' => $this->newAlias,
            'requested_replicas' => $this->requestedReplicas,
        ];
    }

    public static function unpack(ElasticsearchConnection $connection, array $packed): static
    {
        return new static($connection, $packed['source'], $packed['dest'], $packed['old_alias'], $packed['new_alias'], $packed['requested_replicas']);
    }

    protected function runningTasks(): array
    {
        $nodes = $this->tasksAPICall()->json('nodes');
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
        return ! in_array($this->task, $this->runningTasks());
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
