<?php

namespace App\Models;

use App\Models\Model;

class ApiToken extends Model
{
    public const ADMIN = 'admin';

    public const READ_ONLY = 'read_only';

    public function project()
    {
        $this->belongsTo(Project::class);
    }
}
