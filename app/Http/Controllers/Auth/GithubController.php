<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\User as TwoUser;

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

    public function redirect()
    {
        return $this->github()->redirect();
    }

    public function handle()
    {
        $githubUser = $this->github()->user();

        $user = $this->findUser($githubUser->getEmail());

        if ($user instanceof User) {
            return $this->loginUser($user);
        }

        return $this->populateAndRedirectToSignUp($githubUser);
    }

    private function findUser(string $email)
    {
        return User::firstWhere([
            'email' => $email,
            'github' => true
        ]);
    }

    private function loginUser(User $user)
    {
        Auth::login($user, true);

        return redirect()->intended('dashboard');
    }

    private function populateAndRedirectToSignUp($githubUser)
    {
        request()->session()->put('githubUser', [
            'name' => $githubUser->getName(),
            'email' => $githubUser->getEmail(),
            'avatar_url' => $githubUser->getAvatar(),
        ]);

        return redirect()->route('sign-up');
    }
}
