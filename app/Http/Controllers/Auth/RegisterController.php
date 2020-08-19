<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUser;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class RegisterController extends Controller
{
    use RegistersUsers;

    protected UserRepository $users;

    /**
     * Where to redirect users after registration.
     */
    protected $redirectTo = '/project/create';

    public function __construct(UserRepository $userRepository)
    {
        $this->middleware('guest');

        $this->users = $userRepository;
    }

    public function showRegistrationForm(Request $request)
    {
        return Inertia::render('auth/register', [
            'githubUser' => $request->session()->get('githubUser', []),
            'paddleData' => [
                'vendor' => (int) config('services.paddle.vendor_id'),
            ],
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
                'avatar_url' => '',
                'github' => false
            ]
        );

        $paylink = $user->newSubscription('hobby', config('services.paddle.plan_id'))
            ->returnTo(route('dashboard'))
            ->create();

        return ['paylink' => $paylink];
    }

    // /**
    //  * Create a new user instance after a valid registration.
    //  *
    //  * @param  array $data
    //  * @return \App\Models\User
    //  */
    // protected function create(array $data)
    // {
    //     $githubUser = session()->get('githubUser', null);

    //     $email = $data['email'];
    //     $password = (isset($data['password'])) ? Hash::make($data['password']) : '';
    //     $avatar_url = '';

    //     if ($githubUser !== null) {
    //         $avatar_url = $githubUser['avatar_url'];
    //     } else {
    //         $avatar_url = 'https://www.gravatar.com/avatar/';
    //         $avatar_url .= md5(strtolower(trim($email)));
    //     }

    //     return User::create(
    //         [
    //             'email' => $email,
    //             'username' => $data['username'],
    //             'password' => $password,
    //             'avatar_url' => $avatar_url,
    //             'github' => $githubUser !== null
    //         ]
    //     );
    // }
}
