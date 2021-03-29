<?php

namespace App\Models;

use App\Models\Model;

class AllowedIp extends Model
{
    public function cluster()
    {
        return $this->belongsTo(Cluster::class);
    }
}
