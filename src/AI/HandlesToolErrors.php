<?php

declare(strict_types=1);

namespace Sigmie\AI;

use Sigmie\Base\ElasticsearchException;
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
        } catch (Throwable $throwable) {
            return json_encode(
                ['error' => $this->toolErrorMessage($throwable)],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
            );
        }
    }

    protected function toolErrorMessage(Throwable $e): string
    {
        $reason = trim($e->getMessage());

        // Elasticsearch errors often arrive as a JSON body — surface the human-readable reason.
        $decoded = json_decode($reason, true);

        if (is_array($decoded)) {
            if (($decoded['json']['timed_out'] ?? $decoded['timed_out'] ?? false) === true) {
                return 'Elasticsearch timed out before completing the search.';
            }

            $cluster = $decoded['json']['error'] ?? $decoded['error'] ?? null;

            $surfaced = is_array($cluster) ? ($cluster['root_cause'][0]['reason'] ?? $cluster['reason'] ?? null) : $cluster;

            if (is_string($surfaced) && $surfaced !== '') {
                $reason = $surfaced;
            } elseif ($e instanceof ElasticsearchException) {
                $reason = 'Elasticsearch could not complete the search.';
            }
        }

        return $reason." Check the field names and the filter/sort syntax in this tool's description, then try again.";
    }
}
