<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;

class LandingController extends Controller
{
    /**
     * Render landing page
     */
    public function __invoke()
    {
        return Inertia::render('landing');
    }
}
