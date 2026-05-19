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
}
