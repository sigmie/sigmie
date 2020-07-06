<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;

class LandingController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke()
    {
        return Inertia::render('landing');
    }
}
