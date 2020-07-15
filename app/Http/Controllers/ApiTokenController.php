<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class ApiTokenController extends Controller
{
    /**
     * Api tokens index view
     */
    public function index()
    {
        return Inertia::render('api-token/index');
    }
}
