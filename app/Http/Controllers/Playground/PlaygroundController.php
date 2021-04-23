<?php

declare(strict_types=1);

namespace App\Http\Controllers\Playground;

use App\Models\IndexingActivity;
use App\Models\IndexingPlan;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Sigmie\Base\APIs\Calls\Mapping;

class PlaygroundController extends \App\Http\Controllers\Controller
{
    use Mapping;

    public function __invoke(Project $project, Request $request)
    {
        $this->authorize('view', $project);

        $cluster = $project->clusters->first();

        $this->setHttpConnection($cluster->newHttpConnection());

        $res = $this->mappingAPICall('*');

        return Inertia::render(
            'playground/playground',
            ['indices'=> $res->json()]
        );
    }
}
