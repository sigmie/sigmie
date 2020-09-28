<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateUser;
use App\Models\User;

class UserController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(User::class, 'user');
    }

    public function update(UpdateUser $request, User $user)
    {
        $data = $request->validated();

        $user->update($data);

        return redirect()->route('account.settings', ['section' => 'account']);
    }
}
