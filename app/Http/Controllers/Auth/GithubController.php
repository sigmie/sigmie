<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\Request;

class GithubController extends Controller
{
    /**
     * Guest controller
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
}
