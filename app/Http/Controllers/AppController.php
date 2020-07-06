<?php declare(strict_types=1);

namespace App\Http\Controllers;


class AppController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
}
