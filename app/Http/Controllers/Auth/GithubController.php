<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GithubProvider;

class GithubController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest');
    }

    protected function github(): GithubProvider
    {
        return Socialite::driver('github');
    }

    public function redirect(Request $request)
    {
        $action = $request->get('action');

        return $this->github()
            ->with(
                ['redirect_uri' => route("github.{$action}")]
            )->redirect();
    }

    /**
     * Populate session with Github infos
     */
    public function register()
    {
        $githubUser = $this->github()->user();

        $this->populateSession($githubUser);

        return redirect()->route('register');
    }

    public function login()
    {
        // $githubUser = Socialite::driver('github')->user();
        // $email = $githubUser->getEmail();

        // $user = User::where(['email' => $email, 'github' => true])->first();

        // if ($user !== null) {
        //     Auth::login($user, true);

        //     return redirect()->intended('dashboard');
        // }

        // $this->populateSession($githubUser);

        // return redirect(route('register'));
    }

    /**
     * Populate session values from
     * the given github user data
     *
     * @param SocialiteUser $githubUser
     * @return void
     */
    protected function populateSession(SocialiteUser $githubUser)
    {
        request()->session()->put('githubUser', [
            'name' => $githubUser->getName(),
            'email' => $githubUser->getEmail(),
            'avatar_url' => $githubUser->getAvatar(),
        ]);
    }
}
