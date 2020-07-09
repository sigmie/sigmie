<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ClusterHasFailed;
use App\Events\ClusterWasBooted;
use App\Events\ClusterWasCreated;
use App\Models\Cluster;
use App\Repositories\ClusterRepository;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class PollState implements ShouldQueue
{
    public $tries = 30;

    public $retryAfter = 15;

    public $delay = 90;

    private $clusters;

    public function __construct(ClusterRepository $clusterRepository)
    {
        $this->clusters = $clusterRepository;
    }

    public function handle(ClusterWasCreated $event): void
    {
        $domain = config('services.cloudflare.domain');

        $cluster = $this->clusters->find($event->clusterId);

        $password = decrypt($cluster->password);

        /** @var  Response */
        $response = Http::withBasicAuth($cluster->username, $password)->timeout(3)->get("https://{$cluster->name}.{$domain}");

        if ($response->getStatusCode() === 200) {

            $this->clusters->update($event->clusterId, ['state' => Cluster::RUNNING]);

            event(new ClusterWasBooted($event->clusterId));

            return;
        }

        throw new \Exception("Cluster run check failed after {$this->tries} tries with {$this->retryAfter} delay between each of them.");
    }

    public function failed(ClusterWasCreated $event, Exception $exception): void
    {
        $this->clusters->update($event->clusterId, ['state' => Cluster::FAILED]);

        event(new ClusterHasFailed($event->clusterId));
    }
}
