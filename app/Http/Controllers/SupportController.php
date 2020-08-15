<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class SupportController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('support/index');
    }

    public function send(Request $request)
    {
        return;
    }
}
