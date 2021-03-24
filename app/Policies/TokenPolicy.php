<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AbstractCluster;
use App\Models\Cluster;
use App\Models\Token;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TokenPolicy
{
    use HandlesAuthorization;

    public function index(User $user, AbstractCluster $cluster)
    {
        return $cluster->findUser()->getAttribute('id') === $user->getAttribute('id');
    }

    /**
     * Cluster belongs to auth user
     * Token belongs to cluster
     *
     */
    public function update(User $user, Token $token, AbstractCluster $cluster)
    {
        return $this->clusterBelongsToUser($user, $cluster) && $this->tokenBelongsToCluster($token, $cluster);
    }

    private function clusterBelongsToUser(User $user, AbstractCluster $cluster)
    {
        return $cluster->findUser()->getAttribute('id') === $user->getAttribute('id');
    }

    private function tokenBelongsToCluster(Token $token, AbstractCluster $cluster): bool
    {
        return $token->getAttribute('tokenable_id') === $cluster->getAttribute('id');
    }
}
