<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
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
        $cluster = $project->clusters()->first();
        $clusterId = null;

        if ($cluster !== null){
            $clusterId = $cluster->id;
        }

        return Inertia::render('settings/index', ['clusterId' => $clusterId]);
    }
}
