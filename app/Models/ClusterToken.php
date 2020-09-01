<?php

declare(strict_types=1);

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken;

class ClusterToken extends PersonalAccessToken
{
    protected $table = 'cluster_tokens';
}
