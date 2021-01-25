<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\Subscription;
use App\Models\User;

trait WithNotSubscribedUser
{
    private User $user;

    private function withNotSubscribedUser()
    {
        $this->user = User::factory()->create();
    }
}
