<?php

declare(strict_types=1);

namespace App\Http\Controllers\Indices;

use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Sigmie\Base\APIs\Calls\Mapping;
use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Base\Index\Index;

class IndexController extends \App\Http\Controllers\Controller
{
    use Mapping, IndexActions;

    public function index(Project $project, Request $request)
    {
        $this->authorize('view', $project);

        $cluster = $project->clusters->first();

        $this->setHttpConnection($cluster->newHttpConnection());

        $res = $this->mappingAPICall('*');

        return Inertia::render(
            'indices/index',
        );
    }

    public function store(Project $project, Request $request)
    {
        $timestamp = Carbon::now()->format('YmdHis');

        $indexName = "sigmie_{$timestamp}";

        $index = new Index($indexName,);

        $cluster = $project->clusters->first();

        $this->setHttpConnection($cluster->newHttpConnection());

        $this->createIndex($index);

        $index->setAlias($request->get('name'));

        return;
    }
}
