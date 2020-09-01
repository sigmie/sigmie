<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function update(User $authenticated, User $user): bool
    {
        return $user->getAttribute('id') === $authenticated->getAttribute('id');
    }
}
