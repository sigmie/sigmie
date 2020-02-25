<?php

namespace App\Http\Controllers\Auth;

use App\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;
use Laravel\Socialite\Facades\Socialite;

class RegisterController extends Controller
{
    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Show the application registration form.
     *
     * @return \Illuminate\View\View
     */
    public function showRegistrationForm()
    {
        $intent = (new User)->createSetupIntent();

        return view('auth.register', ['app' => ['intent' => $intent]]);
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make(
            $data,
            [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            ]
        );
    }

    /**
     * Handle Github authentication callback
     *
     * @return void
     */
    public function handleCallback()
    {
        $user = Socialite::driver('github')->user();

        dd('register');

        return redirect(route('register'));
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array $data
     * @return \App\User
     */
    protected function create(array $data)
    {
        $user = User::create(
            [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            ]
        );

        $user->newSubscription('hobby', 'plan_GES1izXya6Q2bA')->trialDays(15)->create($data['method']);

        return $user;
    }
}
