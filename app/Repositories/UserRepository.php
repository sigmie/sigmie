<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use App\Repositories\BaseRepository;

/**
 * @package App\Repositories
 */
class UserRepository extends BaseRepository
{
    public function __construct(User $user)
    {
        parent::__construct($user);
    }
}
