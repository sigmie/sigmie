<?php

declare(strict_types=1);

namespace App\Gates;

use App\Models\Project;
use App\Models\User;

class ClusterGate
{
    public function create(User $user, Project $project)
    {
        $projectBelongsToUser = $project->user->id === $user->id;
        $projectHasNotCluster = $project->clusters()->withTrashed()->get()->isEmpty();

        return $projectBelongsToUser && $projectHasNotCluster;
    }
}
