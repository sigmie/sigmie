<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserPassword;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class PasswordController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(User::class, 'user');
    }

    public function update(UpdateUserPassword $request, User $user)
    {
        $data = $request->validated();

        $oldPasswordMatches = Hash::check($data['old_password'], $user->getAttribute('password'));

        if ($oldPasswordMatches) {

            $hash = Hash::make($data['new_password']);

            $user->update(['password' => $hash]);

            $request->session()->flash('success', 'Your password was updated.');

            return redirect()->route('account.settings', ['section' => 'account']);
        }

        $request->session()->flash('error', 'Password does not match');

        return redirect()->route('account.settings', ['section' => 'account']);
    }
}
