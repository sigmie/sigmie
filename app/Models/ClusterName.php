<?php

namespace App\Models;

use App\Models\Model;

class ClusterName extends Model
{
    public function cluster()
    {
        return $this->morphTo();
    }
}
