<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index(string $section = 'account')
    {
        $data = [];

        $data['account'] = Auth::user()->only(['username', 'email', 'avatar_url','created_at']);


        return Inertia::render('account/settings', ['section' => $section, 'data' => $data]);
    }
}
