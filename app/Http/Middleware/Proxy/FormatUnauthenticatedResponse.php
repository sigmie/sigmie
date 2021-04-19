<?php

declare(strict_types=1);

namespace App\Http\Middleware\Proxy;

use App\Http\Controllers\Cluster\TokenController;
use App\Models\AbstractCluster;
use Closure;

class FormatUnauthenticatedResponse
{
    /**
     * @param  \Illuminate\Http\Request  $request
     */
    public function handle($request, Closure $next)
    {

        return response()->json(['error' => 401, 'status' => 'Unauthenticated.'], 401);
    }
}
