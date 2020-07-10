<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Project;
use App\Repositories\BaseRepository;

class ProjectRepository extends BaseRepository
{
    public function __construct(Project $project)
    {
        parent::__construct($project);
    }
}
