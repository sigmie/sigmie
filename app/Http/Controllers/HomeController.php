<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class HomeController extends AppController
{
    public function index()
    {
        return  view('home.index');
    }

    public function show()
    {
        return  view('home.show');
    }
}
