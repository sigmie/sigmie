<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Carbon\Carbon;
use RuntimeException;
use Sigmie\Base\APIs\Reindex;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\ElasticsearchException;

class IndexUpdateTask
{
    use Actions;
    use Reindex;

    protected string $task;

    public function __construct(
        ElasticsearchConnection $elasticsearchConnection,
        public readonly string $source,
        public readonly string $dest,
        public readonly string $oldAlias,
        public readonly string $newAlias,
        public readonly ?int $requestedReplicas,
        public readonly ?string $requestedRefresh = null,
        ?string $task = null,
        private readonly array $deferredBlocks = [],
        private readonly bool $sourceWasWriteBlocked = false,
        private readonly ?string $finalPipeline = null,
        private readonly bool $ownsUpdateLock = false,
    ) {
        $this->setElasticsearchConnection($elasticsearchConnection);

        $task ??= $this->reindexAPICall($source, $dest, waitForCompletion: false, pipeline: '_none')->json('task');

        if (! is_string($task) || $task === '') {
            throw new RuntimeException('Reindex did not return a task ID.');
        }

        $this->task = $task;
    }

    public function pack(): array
    {
        $packed = [
            'source' => $this->source,
            'dest' => $this->dest,
            'old_alias' => $this->oldAlias,
            'new_alias' => $this->newAlias,
            'requested_replicas' => $this->requestedReplicas,
            'task' => $this->task,
        ];

        if ($this->requestedRefresh !== null) {
            $packed['requested_refresh'] = $this->requestedRefresh;
        }

        if ($this->deferredBlocks !== []) {
            $packed['deferred_blocks'] = $this->deferredBlocks;
        }

        if ($this->sourceWasWriteBlocked) {
            $packed['source_was_write_blocked'] = true;
        }

        if ($this->finalPipeline !== null) {
            $packed['final_pipeline'] = $this->finalPipeline;
        }

        if ($this->ownsUpdateLock) {
            $packed['owns_update_lock'] = true;
        }

        return $packed;
    }

    public static function unpack(ElasticsearchConnection $connection, array $packed): static
    {
        $task = $packed['task'] ?? null;
        if (! is_string($task) || $task === '') {
            throw new RuntimeException('Packed index update is missing its task ID.');
        }

        return new static(
            $connection,
            $packed['source'],
            $packed['dest'],
            $packed['old_alias'],
            $packed['new_alias'],
            $packed['requested_replicas'],
            $packed['requested_refresh'] ?? null,
            $task,
            $packed['deferred_blocks'] ?? [],
            $packed['source_was_write_blocked'] ?? false,
            $packed['final_pipeline'] ?? null,
            $packed['owns_update_lock'] ?? false,
        );
    }

    private function taskStatus(): array
    {
        try {
            $status = $this->indexAPICall('_tasks/'.$this->task, 'GET')->json();
        } catch (ElasticsearchException $elasticsearchException) {
            // A missing result can reappear on retry. Reopening writes before
            // then would let finish() promote a copy made before those writes.
            if ($elasticsearchException->json('completed') === true || $elasticsearchException->json('json.completed') === true) {
                $this->restoreSourceWrites();
            }

            throw $elasticsearchException;
        }

        if (! is_array($status) || ! is_bool($status['completed'] ?? null)) {
            throw new RuntimeException('Reindex task status did not report whether it completed.');
        }

        if ($status['completed']) {
            try {
                $this->assertReindexSucceeded($status['response'] ?? null);
            } catch (RuntimeException $elasticsearchException) {
                $this->restoreSourceWrites();

                throw $elasticsearchException;
            }
        }

        return $status;
    }

    private function restoreSourceWrites(): void
    {
        if ($this->ownsUpdateLock) {
            $this->releaseUpdate($this->source, $this->dest, $this->sourceWasWriteBlocked);

            return;
        }

        // A competing successful update may already have removed the source.
        if (! $this->sourceWasWriteBlocked && $this->indexExists($this->source)) {
            $source = new AliasedIndex($this->source, $this->oldAlias);
            $source->setElasticsearchConnection($this->elasticsearchConnection);
            $source->enableWrite();
        }
    }

    public function isCompleted(): bool
    {
        return $this->taskStatus()['completed'];
    }

    public function finish(): AliasedIndex
    {
        return $this->finishUpdate();
    }

    private function finishUpdate(bool $completed = false): AliasedIndex
    {
        $alreadyPromoted = $this->aliasIndices($this->newAlias) === [$this->dest];
        if (! $alreadyPromoted) {
            if (! $completed && ! $this->isCompleted()) {
                throw new RuntimeException('Index update is not completed.');
            }

            // A promotion retry must still have the original write block.
            $this->restoreReindexSettings($this->dest, $this->requestedReplicas, $this->requestedRefresh, $this->deferredBlocks, $this->finalPipeline);
            $marker = $this->ownsUpdateLock ? $this->updateSourceAlias($this->dest) : null;
            try {
                $this->moveAlias(
                    $this->newAlias, [$this->source], $this->dest, $this->oldAlias,
                    pendingAlias: $this->ownsUpdateLock ? $this->updateLock($this->source) : null,
                    markRetired: true, sourceMarker: $marker,
                );
            } catch (ElasticsearchException $exception) {
                if ($marker !== null && $exception->getCode() === 404 && ! $this->indexExists($marker)) {
                    $this->releaseUpdate($this->source, $this->dest, $this->sourceWasWriteBlocked);
                }

                throw $exception;
            }
        }

        $this->deleteRetiredIndices([$this->source], $this->dest, retry: $alreadyPromoted);

        return $this->promotedIndex($this->newAlias, $this->dest);
    }

    public function waitAndFinish(
        ?Carbon $startPollingAt = null,
        int $maxTries = 100,
    ): void {
        $startPollingAt ??= Carbon::now();
        $tries = 0;

        while (true) {
            // @codeCoverageIgnoreStart
            if (Carbon::now()->isBefore($startPollingAt)) {

                sleep(1);

                continue;
            }

            // @codeCoverageIgnoreEnd

            try {
                $completed = $this->isCompleted();
            } catch (ElasticsearchException $exception) {
                if ($exception->getCode() !== 404 || $exception->json('type') !== 'resource_not_found_exception'
                    || $this->aliasIndices($this->newAlias) !== [$this->dest]) {
                    throw $exception;
                }

                $this->deleteRetiredIndices([$this->source], $this->dest, retry: true);

                return;
            }

            if ($completed) {
                $this->finishUpdate(completed: true);

                return;
            }

            $tries++;

            if ($tries > $maxTries) {
                throw new RuntimeException('Index update is not completed. Tried '.$tries.' times.');
            }

            sleep(1);
        }
    }
}
