<?php

declare(strict_types=1);

namespace Sigmie\AI;

/**
 * Adds a toTool() factory method to a SigmieIndex.
 *
 * Requires `laravel/ai` to be installed.
 *
 * Usage:
 *   class ProductIndex extends SigmieIndex
 *   {
 *       use AsTool;
 *       // ...
 *   }
 *
 *   // In your agent:
 *   public function tools(): array
 *   {
 *       return [
 *           app(ProductIndex::class)->toTool(),
 *           app(OrderIndex::class)->toTool(baseFilters: "user_id:{$this->user->id}"),
 *       ];
 *   }
 */
trait AsTool
{
    public function toTool(string $baseFilters = ''): SigmieIndexTool
    {
        return new SigmieIndexTool($this, $baseFilters);
    }

    /**
     * The full agent tool suite for this index: search, on-demand value discovery, and sample
     * documents. Register these with your agent so it can search, learn a field's valid values
     * when it doesn't know them, and inspect example documents to understand the data shape.
     *
     * @return array{0: SigmieIndexTool, 1: SigmieFilterValuesTool, 2: SigmieSampleDocumentsTool}
     */
    public function toTools(string $baseFilters = ''): array
    {
        return [
            new SigmieIndexTool($this, $baseFilters),
            new SigmieFilterValuesTool($this, $baseFilters),
            new SigmieSampleDocumentsTool($this, $baseFilters),
        ];
    }
}
