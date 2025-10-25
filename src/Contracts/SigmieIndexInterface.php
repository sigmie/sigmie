<?php

declare(strict_types=1);

namespace Sigmie\Contracts;

use Sigmie\Document\Document;
use Sigmie\Index\AliasedIndex;
use Sigmie\Index\Index;
use Sigmie\Mappings\NewProperties;

interface SigmieIndexInterface
{
    /**
     * Get the index name
     */
    public function name(): string;

    /**
     * Get the properties blueprint for this index
     */
    public function properties(): NewProperties;

    /**
     * Create the index in Elasticsearch
     */
    public function create(): AliasedIndex;

    /**
     * Delete the index from Elasticsearch
     */
    public function delete(): void;

    /**
     * Update the index with a new configuration
     */
    public function update(callable $callback): AliasedIndex;

    /**
     * Convert data to Document instances
     *
     * @param  array  $data  Array of data to convert to documents
     * @return Document[]
     */
    public function toDocuments(array $data): array;

    /**
     * Get the underlying index instance
     */
    public function index(): null|AliasedIndex|Index;
}
