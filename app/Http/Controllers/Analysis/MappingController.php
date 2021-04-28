<?php

declare(strict_types=1);

namespace App\Http\Controllers\Analysis;

use App\Models\Cluster;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Sigmie\Base\APIs\Calls\Cat as CatAPI;
use Illuminate\Http\Request;
use Sigmie\Base\APIs\Calls\Mapping;

class MappingController extends \App\Http\Controllers\Controller
{
    use Mapping;

    public function __invoke(Project $project, Request $request)
    {
        $this->authorize('view', $project);

        $index = $request->get('index', '*');

        $cluster = $project->clusters->first();

        $this->setHttpConnection($cluster->newHttpConnection());

        $res = $this->mappingAPICall($index);

        return Inertia::render(
            'analysis/analysis',
            [
                'section'=> 'mapping',
                'data' => $res->json(),
                'indices' => $cluster->aliases()
            ]
        );
    }
}
