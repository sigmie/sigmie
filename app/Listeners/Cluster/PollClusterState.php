<?php

declare(strict_types=1);

namespace App\Listeners\Cluster;

use App\Events\Cluster\ClusterHasFailed;
use App\Events\Cluster\ClusterWasBooted;
use App\Events\Cluster\ClusterWasCreated;
use App\Models\AbstractCluster;
use App\Models\Cluster;
use App\Models\Project;
use App\Repositories\ClusterRepository;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Sigmie\Base\Http\ElasticsearchRequest;

class PollClusterState implements ShouldQueue
{
    public $tries = 10;

    public $backoff = 15;

    public $delay = 15;

    private $clusters;

    public function __construct()
    {
    }

    public function handle(ClusterWasCreated $event): void
    {
        $cluster = Project::find($event->projectId)->clusters->first();

        if ($this->clusterCallWasSuccessful($cluster)) {

            $cluster->update(['state' => Cluster::RUNNING]);

            event(new ClusterWasBooted($event->projectId));

            return;
        }

        throw new Exception(
            "Cluster run check failed after {$this->tries} tries with {$this->backoff} delay between each of them."
        );
    }

    public function failed(ClusterWasCreated $event, Exception $exception): void
    {
        $cluster = Project::findOrFail($event->projectId)->clusters->first();

        $cluster->update(['state' => Cluster::FAILED]);

        event(new ClusterHasFailed($event->projectId));
    }

    private function clusterCallWasSuccessful(AbstractCluster $cluster): bool
    {
        $request = new ElasticsearchRequest('GET', new Uri(''));

        $res = $cluster->newHttpConnection()($request);

        return $res->code() === 200;
    }
}
