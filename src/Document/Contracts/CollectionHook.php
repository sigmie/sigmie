<?php

declare(strict_types=1);

namespace Sigmie\Document\Contracts;

use Sigmie\Document\Document;
use Sigmie\Mappings\Properties;
use Sigmie\Sigmie;

interface CollectionHook
{
    /**
     * Return false to skip this hook for collections whose properties don't include
     * any fields that this hook cares about (e.g. a specific custom field type).
     */
    public function shouldRun(Properties $properties): bool;

    /**
     * Called once before any documents are processed or indexed.
     * Use to ensure required side-indices or other infrastructure exist.
     */
    public function beforeBatch(
        string $indexName,
        Sigmie $sigmie,
        Properties $properties,
        array $apis
    ): void;

    /**
     * Enrich or transform documents before they are written to Elasticsearch.
     * Must return the full (modified) document array.
     *
     * @param  array<int, Document>  $documents
     * @return array<int, Document>
     */
    public function processBatch(
        array $documents,
        Properties $properties,
        array $apis
    ): array;

    /**
     * Called after documents have been indexed.
     * Use for sidecar writes or other post-index work.
     *
     * @param  array<int, Document>  $documents
     */
    public function afterBatch(
        array $documents,
        string $indexName,
        Sigmie $sigmie,
        Properties $properties,
        array $apis
    ): void;
}
