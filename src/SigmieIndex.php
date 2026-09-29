<?php

declare(strict_types=1);

namespace Sigmie;

use Sigmie\Index\Shared\SigmieIndex as SharedSigmieIndex;
use Sigmie\Mappings\NewProperties;

abstract class SigmieIndex
{
    use SharedSigmieIndex;

    public function __construct(
        public readonly Sigmie $sigmie
    ) {}

    public function sigmie(): Sigmie
    {
        return $this->sigmie;
    }

    abstract public function name(): string;

    abstract public function properties(): NewProperties;

    /**
     * Source fields the AI tools never return in document content (search hits, samples,
     * retrieved documents, analytics rows). Elasticsearch drops them from `_source` before the
     * response leaves the cluster, so the agent's own field selection cannot bring them back.
     * The fields stay described and filterable. Facets and discover_filter_values still return
     * their values as buckets. Dotted paths reach nested fields.
     *
     * @return list<string>
     */
    public function exceptFromTools(): array
    {
        return [];
    }
}
