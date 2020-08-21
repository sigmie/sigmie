<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUser;
use App\Repositories\UserRepository;
use Carbon\Carbon;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Laravel\Paddle\Receipt;

class RegisterController extends Controller
{
    use RegistersUsers;

    protected UserRepository $users;

    public function __construct(UserRepository $userRepository)
    {
        $this->middleware('guest');

        $this->users = $userRepository;
    }

    private function gravatarUrl(string $email)
    {
        $avatar_url = 'https://www.gravatar.com/avatar/';
        $avatar_url .= md5(strtolower(trim($email)));
        $avatar_url .= '?d=identicon';

        return $avatar_url;
    }

    public function showRegistrationForm(Request $request)
    {
        return Inertia::render('auth/register', [
            'githubUser' => $request->session()->pull('githubUser', null),
        ]);
    }

    public function createUser(StoreUser $request)
    {
        $data = $request->validated();

        $password = (isset($data['password'])) ? Hash::make($data['password']) : null;

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
}
