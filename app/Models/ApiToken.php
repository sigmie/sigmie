<?php

namespace App\Models;

use App\Models\Model;
use Laravel\Sanctum\HasApiTokens;

class ApiToken extends Model
{
    public const ADMIN = 'admin';

    public const READ_ONLY = 'read_only';

    use HasApiTokens;

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
