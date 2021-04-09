<?php

declare(strict_types=1);

namespace App\Jobs\Cluster;

use App\Models\Cluster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class Maintenance implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle()
    {
        $clusters = Cluster::withTrashed()->where('state', Cluster::RUNNING)
            ->pluck('id')->toArray();

        foreach ($clusters as $clusterId) {
            dispatch(new RefreshCloudflareIps($clusterId));
        }
    }
}
