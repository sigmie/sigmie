<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Project;
use Inertia\Inertia;

class SettingsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return array
     */
    public function index(Request $request)
    {
        $project = Project::find($request->get('project_id'));

        $hasCluster = $project->clusters->first() !== null;

        return Inertia::render('settings/index', ['hasCluster' => $hasCluster]);
    }
}
