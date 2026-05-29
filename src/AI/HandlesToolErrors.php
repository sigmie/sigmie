<?php

declare(strict_types=1);

namespace Sigmie\AI;

use Throwable;

/**
 * Guards the agent-facing handle() of a tool so any failure — a filter/sort parse error, an
 * unknown field, a query error — is returned to the model as a readable JSON {"error": ...}
 * it can self-correct from, instead of throwing and aborting the whole agent turn.
 *
 * Only handle() is guarded. The structured result() methods still throw, so programmatic
 * callers keep normal exception semantics.
 */
trait HandlesToolErrors
{
    /**
     * @param  callable(): string  $work
     */
    protected function guard(callable $work): string
    {
        try {
            return $work();
        } catch (Throwable $e) {
            return json_encode(
                ['error' => $this->toolErrorMessage($e)],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        }
    }

    protected function toolErrorMessage(Throwable $e): string
    {
        $reason = trim($e->getMessage());

        // Elasticsearch errors often arrive as a JSON body — surface the human-readable reason.
        $decoded = json_decode($reason, true);

        if (is_array($decoded)) {
            $reason = $decoded['error']['root_cause'][0]['reason']
                ?? $decoded['error']['reason']
                ?? $reason;
        }

        return $reason." Check the field names and the filter/sort syntax in this tool's description, then try again.";
    }
}
