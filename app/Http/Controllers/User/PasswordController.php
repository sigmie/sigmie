<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdatePassword;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class PasswordController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(User::class, 'user');
    }

    public function update(UpdatePassword $request, User $user)
    {
        $data = $request->validated();

        $oldPasswordMatches = Hash::check($data['old_password'], $user->getAttribute('password'));

        if ($oldPasswordMatches) {
            $hash = Hash::make($data['new_password']);

            $user->update(['password' => $hash]);

            return redirect()->route('sign-in', ['password_updated' => true]);
        }

        $request->session()->flash('error', 'Password does not match');

        return redirect()->route('account.settings', ['section' => 'account']);
    }
}
