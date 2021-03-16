<?php

declare(strict_types=1);

namespace App\Listeners\Cluster;

use App\Events\Cluster\ClusterHasFailed;
use App\Events\Cluster\ClusterWasBooted;
use App\Events\Cluster\ClusterWasCreated;
use App\Models\Cluster;
use App\Repositories\ClusterRepository;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class PollClusterState implements ShouldQueue
{
    public $tries = 10;

    public $backoff = 15;

    public $delay = 15;

    private $clusters;

    public function __construct(ClusterRepository $clusterRepository)
    {
        $this->clusters = $clusterRepository;
    }

    public function handle(ClusterWasCreated $event): void
    {
        $cluster = $this->clusters->find($event->clusterId);

        if ($this->clusterCallWasSuccessful($cluster)) {
            $this->clusters->update($event->clusterId, ['state' => Cluster::RUNNING]);

            event(new ClusterWasBooted($event->clusterId));

            return;
        }

        throw new Exception(
            "Cluster run check failed after {$this->tries} tries with {$this->backoff} delay between each of them."
        );
    }

    public function failed(ClusterWasCreated $event, Exception $exception): void
    {
        $this->clusters->update($event->clusterId, ['state' => Cluster::FAILED]);

        event(new ClusterHasFailed($event->clusterId));
    }

    private function clusterCallWasSuccessful(Cluster $cluster): bool
    {
        $port = 8066;
        $domain = config('services.cloudflare.domain');
        $url = "https://proxy.{$cluster->name}.{$domain}:{$port}";
        $client = new Client();

        $response = $client->request('GET', 'https://proxy.test.sigmie.xyz:8066', [
            'verify' => false,
            'json' => [],
            'cert' => storage_path('app/proxy/proxy.crt'),
            'ssl_key' => storage_path('app/proxy/proxy.key')
        ]);

        return $response->getStatusCode() === 200;
    }
}
