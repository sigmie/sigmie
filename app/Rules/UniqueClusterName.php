<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Cluster;
use App\Models\ExternalCluster;
use Illuminate\Contracts\Validation\Rule;

class UniqueClusterName implements Rule
{
    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $name)
    {
        $externalClusters = ExternalCluster::where('name', $name)->get();
        $internalClusters = Cluster::where('name', $name)->get();

        return $externalClusters->isEmpty() && $internalClusters->isEmpty();
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'This Cluster name has already been taken.';
    }
}
