<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Cluster;

class ClusterValidationController extends Controller
{
    public function name(string $name)
    {
        /** @var  Cluster|null */
        $cluster = Cluster::withTrashed()->firstWhere('name', $name);

        $valid = ($cluster instanceof Cluster) ? true : false;

        return response()->json(['valid' => $valid]);
    }
}
