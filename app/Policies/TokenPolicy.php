<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Cluster;
use App\Models\Token;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TokenPolicy
{
    use HandlesAuthorization;

    public function index(User $user, Cluster $cluster)
    {
        return $cluster->findUser()->getAttribute('id') === $user->getAttribute('id');
    }

    /**
     * Cluster belongs to auth user
     * Token belongs to cluster
     *
     */
    public function update(User $user, Token $token, Cluster $cluster)
    {
        return $this->clusterBelongsToUser($user, $cluster) && $this->tokenBelongsToCluster($token, $cluster);
    }

    private function clusterBelongsToUser(User $user, Cluster $cluster)
    {
        return $cluster->findUser()->getAttribute('id') === $user->getAttribute('id');
    }

    private function tokenBelongsToCluster(Token $token, Cluster $cluster): bool
    {
        return $token->getAttribute('tokenable_id') === $cluster->getAttribute('id');
    }
}
