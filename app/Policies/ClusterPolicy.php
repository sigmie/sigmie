<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AbstractCluster;
use App\Models\Cluster;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClusterPolicy
{
    use HandlesAuthorization;

    /**
     * There is no cluster view action
     */
    public function viewAny(User $user)
    {
        return false;
    }

    /**
     * There is no cluster view
     */
    public function view(User $user, AbstractCluster $cluster)
    {
        return false;
    }

    /**
     * Check if the user already owns a project with a cluster
     */
    public function create(User $user): bool
    {
        return $user->projects()->first()->clusters->isEmpty()
            && $user->isSubscribed();
    }

    /**
     * Determine whether the user can update the cluster.
     */
    public function update(User $user, AbstractCluster  $cluster): bool
    {
        return $cluster->isOwnedBy($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AbstractCluster  $cluster): bool
    {
        return $cluster->isOwnedBy($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AbstractCluster $cluster): bool
    {
        return $cluster->isOwnedBy($user) && $user->isSubscribed();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AbstractCluster $cluster): bool
    {
        return false;
    }
}
