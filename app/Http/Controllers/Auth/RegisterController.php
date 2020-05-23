<?php

namespace App\Http\Controllers\Auth;

use App\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RegisterController extends Controller
{
    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/cluster/create';

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
            'paddleVendor' => config('services.paddle.vendor_id'),
            'paddlePlans' => (env('APP_ENV') === 'production') ? config('services.paddle.plans') : config('services.paddle.test_plans')
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
                'name' => ['required', 'string', 'max:255'],
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
     * @return \App\User
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

        $user = User::create(
            [
                'name' => $data['name'],
                'email' => $email,
                'username' => $data['username'],
                'password' => $password,
                'avatar_url' => $avatar_url,
                'github' => $githubUser !== null
            ]
        );

        $plans = config('cashier.plans');
        $user->newSubscription('Subscription', $plans[$data['plan']])->trialDays(14)->create($data['method']);

        return $user;
    }
}
