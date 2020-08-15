<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class RegisterController extends Controller
{
    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/project/create';

    /**
     * Create a new controller instance.
     *
     * @return void */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Show the application registration form.
     *
     * @return \Illuminate\View\View
     */
    public function showRegistrationForm(Request $request)
    {
        return Inertia::render('auth/register', [
            'githubUser' => $request->session()->get('githubUser', []),
            'paddleData' => [
                'vendor' => (int) config('services.paddle.vendor_id'),
                'plan' => (int) config('services.paddle.plan_id')
            ],
        ]);
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        $password = (session()->has('githubUser')) ? [] : ['required', 'string', 'min:8'];

        return Validator::make(
            $data,
            [
                'username' => ['required', 'string', 'min:4'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'password' => $password
            ]
        );
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array $data
     * @return \App\Models\User
     */
    protected function create(array $data)
    {
        $githubUser = session()->get('githubUser', null);

        $email = $data['email'];
        $password = (isset($data['password'])) ? Hash::make($data['password']) : '';
        $avatar_url = '';

        if ($githubUser !== null) {
            $avatar_url = $githubUser['avatar_url'];
        } else {
            $avatar_url = 'https://www.gravatar.com/avatar/';
            $avatar_url .= md5(strtolower(trim($email)));
        }

        return User::create(
            [
                'email' => $email,
                'username' => $data['username'],
                'password' => $password,
                'avatar_url' => $avatar_url,
                'github' => $githubUser !== null
            ]
        );
    }
}
