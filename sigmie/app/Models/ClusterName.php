<?php declare(strict_types=1);

namespace App\Models;

class ClusterName extends Model
{
    public function cluster()
    {
        return $this->morphTo();
    }
}
