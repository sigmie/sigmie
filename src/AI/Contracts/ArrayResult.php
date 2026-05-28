<?php

declare(strict_types=1);

namespace Sigmie\AI\Contracts;

use Laravel\Ai\Tools\Request;

/**
 * A Laravel AI {@see \Laravel\Ai\Contracts\Tool} that can also expose its result as a structured
 * array — for callers that want data rather than the JSON string handle() is required to return.
 *
 * Implementations should build the array in result() and have handle() serialise it, so the data
 * is produced once and there's no encode → decode round-trip for consumers that want the array.
 */
interface ArrayResult
{
    /**
     * @return array<array-key, mixed>
     */
    public function result(Request $request): array;
}
