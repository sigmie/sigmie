<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\IndexingPlan;
use RuntimeException;

class IndexingException extends RuntimeException
{
    public function __construct($message, public IndexingPlan $plan)
    {
        parent::__construct($message);
    }

    public static function copy(string $fetchLocation, IndexingPlan $plan)
    {
        return new static("Failed to transfer file form {$fetchLocation} for indexing plan {$plan->name}.", $plan);
    }

    public static function filesize(string $fetchLocation, string $allowedGb, IndexingPlan $plan)
    {
        return new static("
        File fetched from {$fetchLocation} is bigger than {$allowedGb} GB for indexing plan {$plan->name}.", $plan);
    }

    public static function json(string $fetchLocation, IndexingPlan $plan)
    {
        return new static("File fetched from {$fetchLocation} isn't a valid JSON. For indexing plan $plan->name.", $plan);
    }
}
