<?php

declare(strict_types=1);

namespace Sigmie\Base\Exceptions;

use Exception;

class ElasticsearchException extends Exception
{
    public function __construct(public array $json)
    {
        parent::__construct(json_encode($json));
    }

    public static function fromType(string|null $type, array $json)
    {
        return match ($type) {
            'index_not_found_exception' => new IndexNotFoundException($json),
            'illegal_argument_exception' => new IllegalArgumentException($json),
            'version_conflict_engine_exception' => new VersionConflictEngineException($json),
            'document_missing_exception' => new DocumentMissingException($json),
            'cluster_block_exception' => new ClusterBlockException($json),
            default => new ElasticsearchException($json)
        };
    }
}
