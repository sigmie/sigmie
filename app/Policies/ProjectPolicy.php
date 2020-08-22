<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjectPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return false;
    }

    public function view(User $user, Project $project)
    {
        return false;
    }

    /**
     * @return bool
     */
    public function create(User $user)
    {
        return $user->projects()->get()->isEmpty();
    }

    public function update(User $user, Project $project)
    {
        return false;
    }

    public function delete(User $user, Project $project)
    {
        return false;
    }

    public function restore(User $user, Project $project)
    {
        return false;
    }

    public function forceDelete(User $user, Project $project)
    {
        return false;
    }
}
