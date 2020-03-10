<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class AppController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function __destruct()
    {
        return  view('home.index');
    }
}
