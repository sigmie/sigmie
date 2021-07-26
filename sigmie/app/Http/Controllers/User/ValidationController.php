<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Models\User;

class ValidationController extends \App\Http\Controllers\Controller
{
    public function email(string $email)
    {
        $user = User::firstWhere('email', $email);

        $valid = ($user instanceof User) ? false : true;

        return response()->json(['valid' => $valid]);
    }
}
