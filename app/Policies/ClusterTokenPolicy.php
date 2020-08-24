<?php

namespace App\Policies;

use App\Models\Cluster;
use App\Models\ClusterToken;
use App\Models\User;
use App\Repositories\UserRepository;
use Faker\Provider\ar_JO\Person;
use Illuminate\Auth\Access\HandlesAuthorization;
use Laravel\Sanctum\PersonalAccessToken;

class ClusterTokenPolicy
{
    use HandlesAuthorization;

    public function index(User $user, Cluster $cluster)
    {
        return $cluster->findUser()->getAttribute('id') === $user->getAttribute('id');
    }

    public function update(User $user, ClusterToken $token, Cluster $cluster)
    {
        return $cluster->findUser()->getAttribute('id') === $user->getAttribute('id')
            && $token->getAttribute('tokenable_id') === $cluster->getAttribute('id');
    }
}
