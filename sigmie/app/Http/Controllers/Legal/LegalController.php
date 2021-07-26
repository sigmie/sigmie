<?php

declare(strict_types=1);

namespace App\Http\Controllers\Legal;

use Inertia\Inertia;

class LegalController extends \App\Http\Controllers\Controller
{
    public function about()
    {
        return Inertia::render('legal/about');
    }

    public function terms()
    {
        return Inertia::render('legal/terms');
    }

    public function privacy()
    {
        return Inertia::render('legal/privacy');
    }

    public function imprint()
    {
        return Inertia::render('legal/imprint');
    }

    public function disclaimer()
    {
        return Inertia::render('legal/disclaimer');
    }
}
