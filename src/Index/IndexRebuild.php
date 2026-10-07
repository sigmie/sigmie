<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Document\AliveCollection;
use Sigmie\Document\Contracts\CollectionHook;
use Sigmie\Index\Actions as IndexActions;
use Sigmie\Shared\UsesApis;

/**
 * A physical index being filled for an alias, not yet live.
 *
 *   startRebuild()      movies_20261007  (no alias, _meta.rebuild_of = movies)
 *   collect()->merge()  from any process; resume with Sigmie::rebuilding()
 *   finish()            refresh, size guard, atomic alias move, drop old
 *   abort()             drop the new index; the alias never moved
 *
 * State lives in the index _meta, so a queued job only needs the alias.
 */
class IndexRebuild
{
    use IndexActions;
    use UsesApis;

    public const META_KEY = 'rebuild_of';

    public const META_REPLICAS = 'rebuild_replicas';

    /**
     * @param  array<int, CollectionHook>  $hooks
     */
    public function __construct(
        ElasticsearchConnection $connection,
        public readonly string $alias,
        public readonly string $name,
        protected int $replicas,
        protected array $hooks = [],
    ) {
        $this->setElasticsearchConnection($connection);
    }

    public function collect(): AliveCollection
    {
        return (new AliveCollection($this->name, $this->elasticsearchConnection))
            ->apis($this->apis)
            ->hooks($this->hooks);
    }

    /**
     * Documents written so far. Refreshes first, so the count is current.
     */
    public function count(): int
    {
        $this->refreshIndex($this->name);

        return $this->collect()->count();
    }

    public function finish(int $minDocuments = 1): AliasedIndex
    {
        $count = $this->count();

        if ($count < $minDocuments) {
            $this->abort();

            throw RebuildTooSmall::forAlias($this->alias, $count, $minDocuments);
        }

        // Serverless: refresh_interval must be -1 or ≥5s and replicas are managed.
        $settings = ['refresh_interval' => $this->elasticsearchConnection->isServerless() ? '5s' : '1s'];

        if (! $this->elasticsearchConnection->isServerless()) {
            $settings['number_of_replicas'] = $this->replicas;
        }

        $this->indexAPICall($this->name.'/_settings', 'PUT', $settings);

        $previousIndices = $this->aliasIndices($this->alias);

        $this->moveAlias($this->alias, $previousIndices, $this->name);

        foreach ($previousIndices as $previousIndex) {
            $this->deleteIndex($previousIndex);
        }

        $index = new AliasedIndex($this->name, $this->alias);
        $index->setElasticsearchConnection($this->elasticsearchConnection);

        return $index;
    }

    public function abort(): void
    {
        $this->deleteIndex($this->name);
    }
}
