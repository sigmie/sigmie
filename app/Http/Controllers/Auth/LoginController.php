<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\User;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
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
        $this->middleware('guest')->except('logout');
    }

    /**
     * Redirect to Github
     *
     * @return Illuminate\Http\RedirectResponse
     */
    public function githubRedirect()
    {
        return Socialite::driver('github')
            ->with(['redirect_uri' => 'https://localhost/github/callback'])
            ->redirect();
    }

    /**
     * Handle Github authentication callback
     *
     * @return void
     */
    public function handleCallback()
    {
        $user = Socialite::driver('github')->user();

        dd('login');

        return redirect(route('register'));
    }
}
