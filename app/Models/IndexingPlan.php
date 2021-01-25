<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class IndexingPlan extends Model
{
    use HasFactory;

    public function cluster()
    {
        return $this->belongsTo(Cluster::class);
    }
}
