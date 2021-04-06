<?php declare(strict_types=1);

namespace App\Models;


class AllowedIp extends Model
{
    public function cluster()
    {
        return $this->belongsTo(Cluster::class);
    }
}
