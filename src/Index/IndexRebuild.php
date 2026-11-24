<?php

declare(strict_types=1);

namespace Sigmie\Index;

use RuntimeException;
use Sigmie\Base\APIs\Count;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Document\AliveCollection;
use Sigmie\Document\Contracts\CollectionHook;
use Sigmie\Index\Actions as IndexActions;
use Sigmie\Shared\UsesApis;

/**
 * A physical index being filled for an alias, not yet live.
 *
 *   startRebuild()      movies_20261007  (hidden pending alias)
 *   collect()->merge()  from any process; resume with Sigmie::rebuilding()
 *   finish()            refresh, size guard, atomic alias move, drop old
 *   abort()             drop the new index; the alias never moved
 *
 * finish() swaps only while the alias still names the indices recorded
 * at start. A second finish already points here and must not delete this
 * index. A slower rebuild, whose alias now names something else, throws
 * and leaves its new index behind.
 *
 * Finish and abort both consume the pending alias in an atomic request.
 * Both decisions can resume cleanup after an unacknowledged deletion.
 */
class IndexRebuild
{
    use Count;
    use IndexActions;
    use UsesApis;

    public const META_KEY = 'rebuild_of';

    public const META_REPLICAS = 'rebuild_replicas';

    public const META_REFRESH = 'rebuild_refresh';

    public const META_BLOCKS = 'rebuild_deferred_blocks';

    public const META_TARGETS = 'rebuild_targets';

    /**
     * $startedOn is the alias targets at startRebuild. Null means this
     * handle did not record them. An empty list means the alias was absent.
     *
     * @param  array<int, CollectionHook>  $hooks
     * @param  array<int, string>|null  $startedOn
     */
    public function __construct(
        ElasticsearchConnection $connection,
        public readonly string $alias,
        public readonly string $name,
        protected ?int $replicas,
        protected array $hooks = [],
        protected ?array $startedOn = null,
        protected ?string $refresh = null,
        protected array $deferredBlocks = [],
        private ?string $fillRouting = null,
    ) {
        $this->setElasticsearchConnection($connection);
    }

    public function collect(): AliveCollection
    {
        return (new AliveCollection(self::pendingAlias($this->name), $this->elasticsearchConnection, requireAlias: true))
            ->apis($this->apis)
            ->hooks($this->hooks);
    }

    public static function pendingAlias(string $index): string
    {
        return '.sigmie-rebuild-pending-'.hash('sha256', $index);
    }

    /**
     * Documents written so far. Refreshes first, so the count is current.
     */
    public function count(): int
    {
        $this->refreshIndex($this->name);

        return $this->countAPICall($this->name)->json('count');
    }

    public function finish(int $minDocuments = 1): AliasedIndex
    {
        $current = $this->aliasIndices($this->alias);
        $alreadyPromoted = $current === [$this->name];

        if (! $alreadyPromoted) {
            if (! $this->sameNames($current, $this->startedOn ?? [])) {
                throw RebuildLost::forAlias($this->alias, $this->name);
            }

            $this->checkRetirementSources($current, $this->alias);
            if (in_array('blocks.read', $this->deferredBlocks, true)) {
                // A previous finish may have restored the block before its alias move failed.
                $this->indexAPICall($this->name.'/_settings', 'PUT', ['blocks.read' => false]);
            }
            $count = $this->count();

            if ($count < $minDocuments) {
                $this->abort();

                throw RebuildTooSmall::forAlias($this->alias, $count, $minDocuments);
            }

            $refresh = $this->refresh;
            if ($refresh === null) {
                $mapping = $this->indexAPICall($this->name.'/_mapping', 'GET')->json();
                $refresh = $mapping[$this->name]['mappings']['_meta'][self::META_REFRESH] ?? null;
            }

            $this->restoreReindexSettings($this->name, $this->replicas, $refresh, $this->deferredBlocks);
            $this->moveAlias($this->alias, $current, $this->name, pendingAlias: self::pendingAlias($this->name),
                markRetired: true, fillRouting: $this->fillRouting);
        }

        $retired = $alreadyPromoted ? ($this->startedOn ?? []) : $current;
        $this->deleteRetiredIndices($retired, $this->name, retry: $alreadyPromoted);

        return $this->promotedIndex($this->alias, $this->name);
    }

    public function abort(): void
    {
        $aborted = '.sigmie-rebuild-aborted-'.hash('sha256', $this->name);

        try {
            $this->applyAliasActions($this->alias, [
                ['remove' => ['index' => $this->name, 'alias' => self::pendingAlias($this->name), 'must_exist' => true]],
                ['add' => ['index' => $this->name, 'alias' => $aborted, 'is_hidden' => true]],
            ]);
        } catch (ElasticsearchException $exception) {
            if ($exception->json('type') === 'index_not_found_exception') {
                return;
            }

            if ($exception->json('type') !== 'aliases_not_found_exception') {
                throw $exception;
            }

            // Only a completed abort can leave this marker. Finish needs the
            // pending alias, which that abort already removed.
            $status = $this->indexAPICall($this->name.'/_alias/'.$aborted, 'HEAD')->code();
            if ($status === 404) {
                return;
            }

            if ($status !== 200) {
                throw new RuntimeException("Could not resolve abort status for '{$this->name}'.");
            }
        }

        $this->ensureIndexDeleted($this->name);
    }

    /**
     * @param  array<int, int|string>  $left
     * @param  array<int, int|string>  $right
     */
    private function sameNames(array $left, array $right): bool
    {
        $names = static function (array $values): array {
            $values = array_map(static fn (int|string $name): string => (string) $name, $values);
            sort($values, SORT_STRING);

            return $values;
        };

        return $names($left) === $names($right);
    }
}
