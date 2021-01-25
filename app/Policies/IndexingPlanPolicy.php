<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\IndexingPlan;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class IndexingPlanPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->isSubscribed();
    }

    public function view(User $user, IndexingPlan $indexingPlan)
    {
        return $indexingPlan->cluster->isOwnedBy($user);
    }

    public function create(User $user)
    {
        return $user->isSubscribed();
    }

    public function update(User $user, IndexingPlan $indexingPlan)
    {
        return $user->isSubscribed()
            && $indexingPlan->cluster->isOwnedBy($user);
    }

    public function delete(User $user, IndexingPlan $indexingPlan)
    {
        return $indexingPlan->cluster->isOwnedBy($user);
    }

    public function restore(User $user, IndexingPlan $indexingPlan)
    {
        return false;
    }

    public function forceDelete(User $user, IndexingPlan $indexingPlan)
    {
        return false;
    }
}
