<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUser;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class RegisterController extends Controller
{
    use RegistersUsers;

    protected UserRepository $users;

    public function __construct(UserRepository $userRepository)
    {
        $this->users = $userRepository;

        $this->middleware('guest');
    }

    public function showRegistrationForm(Request $request)
    {
        return Inertia::render(
            'auth/register',
            [
                'githubUser' => $request->session()->pull('githubUser', null)
            ]
        );
    }

    public function createUser(StoreUser $request)
    {
        $data = $request->validated();

        $password = (isset($data['password'])) ? Hash::make($data['password']) : null;

        /** @var  User */
        $user = $this->users->create(
            [
                'email' => $data['email'],
                'username' => $data['username'],
                'password' => $password,
                'avatar_url' => ($data['github']) ? $data['avatar_url'] : $this->gravatarUrl($data['email']),
                'github' => $data['github']
            ]
        );

        $this->guard()->login($user);

        event(new Registered($user));

        return ['registered' => $user->exists];
    }

    private function gravatarUrl(string $email)
    {
        $avatar_url = 'https://www.gravatar.com/avatar/';
        $avatar_url .= md5(strtolower(trim($email)));
        $avatar_url .= '?d=identicon';

        return $avatar_url;
    }
}
