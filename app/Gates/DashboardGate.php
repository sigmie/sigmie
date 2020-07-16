<?php

declare(strict_types=1);

namespace App\Gates;

use App\Models\Project;
use App\Models\User;

class DashboardGate
{
    public function view(User $user, Project $project)
    {
        return $project->getAttribute('user_id') === $user->getAttribute('id');
    }
}
