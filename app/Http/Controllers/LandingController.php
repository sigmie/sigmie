<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class LandingController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke()
    {
        return Inertia::render('landing');
    }
}
