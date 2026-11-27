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
     * The fields stay described and filterable, but the tools refuse to facet, group, measure or
     * sort by them (or their descendants), so no aggregation returns their values either.
     * Dotted paths reach nested fields. Library APIs such as facets() and analytics() are unaffected.
     *
     * @return list<string>
     */
    public function exceptFromTools(): array
    {
        return [];
    }

    /**
     * Source fields the document tools (search, sample_documents, get_documents) return when the
     * agent passes no `fields` argument. Empty returns every field. Use it to keep large fields,
     * such as a full-text body, out of default results; the agent can still request them by name.
     * exceptFromTools() always wins over both this default and the agent's request.
     *
     * @return list<string>
     */
    public function toolFields(): array
    {
        return [];
    }
}
