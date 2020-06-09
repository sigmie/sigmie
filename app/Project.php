<?php

namespace App;

use App\Cluster;

class Project extends Model
{
    /**
     * Clusters relationship
     *
     * \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function clusters()
    {
        return $this->hasMany(Cluster::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
