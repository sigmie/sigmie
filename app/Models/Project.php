<?php

declare(strict_types=1);

namespace App\Models;

use Cloudflare\API\Auth\APIToken;

class Project extends Model
{
    public function clusters()
    {
        return $this->hasMany(Cluster::class);
    }

    public function apiTokens()
    {
        return $this->hasMany(ApiToken::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // public function productionCluster()
    // {
    //     return $this->hasMany(Cluster::class)->where(['environment' => 'prod']);
    // }

    // public function stagingCluster()
    // {
    //     return $this->hasMany(Cluster::class)->where(['environment' => 'staging']);
    // }

    // public function testCluster()
    // {
    //     return $this->hasMany(Cluster::class)->where(['environment' => 'test']);
    // }
}
