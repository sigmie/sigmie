<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\IndexingPlan;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class IndexingPlanPolicy
{
    use HandlesAuthorization;

    public function create(User $user)
    {
        return $user->isSubscribed();
    }

    public function update(User $user, IndexingPlan $indexingPlan)
    {
        return $user->isSubscribed()
            && $indexingPlan->cluster->isOwnedBy($user);
    }

    public function trigger(User $user, IndexingPlan $indexingPlan)
    {
        return $user->isSubscribed() && $indexingPlan->isActive();
    }

    public function delete(User $user, IndexingPlan $indexingPlan)
    {
        return $indexingPlan->cluster->isOwnedBy($user);
    }
}
