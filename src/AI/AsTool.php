<?php

declare(strict_types=1);

namespace Sigmie\AI;

/**
 * Adds a tools() factory to a SigmieIndex: the agent tool suite for the index.
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
 *           ...app(ProductIndex::class)->tools(),
 *           ...app(OrderIndex::class)->tools("user_id:{$this->user->id}"),
 *       ];
 *   }
 *
 * The `$baseFilter` is server-controlled scoping: it is AND-ed into every query of every tool
 * and is NEVER taken from the agent, so the agent cannot read outside the scope.
 */
trait AsTool
{
    /**
     * The agent tool suite for this index: search, on-demand value discovery, and sample documents.
     *
     * @return array{0: SigmieIndexTool, 1: SigmieFilterValuesTool, 2: SigmieSampleDocumentsTool}
     */
    public function tools(string $baseFilter = ''): array
    {
        return [
            new SigmieIndexTool($this, $baseFilter),
            new SigmieFilterValuesTool($this, $baseFilter),
            new SigmieSampleDocumentsTool($this),
        ];
    }
}
