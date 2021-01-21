<?php

declare(strict_types=1);

namespace App\Http\Controllers\Indexing;

use App\Models\Cluster;
use App\Models\Project;
use App\Repositories\ClusterRepository;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Sigmie\Base\APIs\Calls\Cat as CatAPI;

class IndexingController extends \App\Http\Controllers\Controller
{
    public function __invoke()
    {

        return Inertia::render('indexing/indexing');
    }
}
