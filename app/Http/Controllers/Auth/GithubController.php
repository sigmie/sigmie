<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\User;
use Exception;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

class GithubController extends Controller
{
    /** * Guest controller
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Redirect to Github login
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirect(Request $request)
    {
        $action = $request->get('action');

        $redirect_uri = ($action === 'register') ? '/github/register' : '/github/login';

        return Socialite::driver('github')
            ->with(['redirect_uri' => config('app.url') . $redirect_uri])
            ->redirect();
    }

    public function register(Request $request)
    {
        $githubUser = Socialite::driver('github')->user();

        $request->session()->put('github', [
            'name' => $githubUser->getName(),
            'email' => $githubUser->getEmail(),
            'avatar_url' => $githubUser->getAvatar(),
            'github' => true
        ]);

        return redirect('register');
    }

    /**
     * Handle Github login
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function login()
    {
        $email = Socialite::driver('github')->user()->getEmail();
        $user = User::where(['email' => $email, 'github' => true])->first();

        if ($user !== null) {
            Auth::login($user, true);

            return redirect()->intended('home');
        }

        return redirect(route('register'));
    }
}
