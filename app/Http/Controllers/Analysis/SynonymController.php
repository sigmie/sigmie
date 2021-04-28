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

class SynonymController extends \App\Http\Controllers\Controller
{
    use Mapping;

    public function __invoke(Project $project, Request $request)
    {
        $this->authorize('view', $project);

        ray($request->all());
        $index = $request->get('index', '*');

        $cluster = $project->clusters->first();

        $this->setHttpConnection($cluster->newHttpConnection());

        $res = $this->mappingAPICall($index);

        ray($index);

        return Inertia::render(
            'analysis/analysis',
            [
                'mapping' => $res->json(),
                'indices' => $cluster->aliases()
            ]
        );
    }
}
