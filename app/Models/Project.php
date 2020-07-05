<?php

namespace App\Models;

use App\Models\Cluster;

class Project extends Model
{
    public function clusters()
    {
        return $this->hasMany(Cluster::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
