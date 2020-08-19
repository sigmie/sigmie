<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Cluster;
use App\Models\User;
use App\Repositories\ClusterRepository;
use App\Repositories\UserRepository;

class UserValidationController extends Controller
{
    private UserRepository $users;

    public function __construct(UserRepository $userRepository)
    {
        $this->users = $userRepository;
    }

    public function email(string $email)
    {
        $user = $this->users->findOneBy('email', $email);

        $valid = ($user instanceof User) ? false : true;

        return response()->json(['valid' => $valid]);
    }
}
