<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke()
    {
        if (Auth::user()->activeProject() === null) {
            return redirect()->route('project.create');
        }

        return Inertia::render('dashboard', ['data' => null]);
    }
}
