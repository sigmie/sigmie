<?php

namespace App\Http\Controllers;

use App\Project;
use App\User;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return array
     */
    public function index()
    {
        /** @var  User */
        $user = Auth::user();

        return $user->projects()->get()->toArray();
    }

    /**
     * Project create page
     */
    public function create()
    {
        $hasProjects = Auth::user()->projects->isEmpty() === false;

        return Inertia::render('project/create', ['hasProjects' => $hasProjects]);
    }

    public function store(Request $request)
    {
    }
}
