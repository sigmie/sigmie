<?php

namespace App\Listeners;

use App\Cluster;
use App\Events\ClusterHasFailed;
use App\Events\ClusterIsRunning;
use App\Events\ClusterWasCreated;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;

class AwaitElasticsearchBoot implements ShouldQueue
{
    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 30;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $retryAfter = 15;

    /**
     * The time (seconds) before the job should be processed.
     *
     * @var int
     */
    public $delay = 90;

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(ClusterWasCreated $event)
    {
        $domain = config('services.cloudflare.domain');

        $cluster = Cluster::find($event->clusterId);

        $password = decrypt($cluster->password);

        $response = Http::withBasicAuth($cluster->username, $password)->timeout(3)->get("https://{$cluster->name}.{$domain}");

        if ($response->getStatusCode() === 200) {

            $cluster->state = Cluster::RUNNING;
            $cluster->save();

            event(new ClusterIsRunning($cluster->id));

            return;
        }

        throw new \Exception("Cluster run check failed after {$this->tries} tries with {$this->retryAfter} delay between each of them.");
    }

    /**
     * Handle a job failure.
     *
     * @param  \App\Exceptions\ClusterFailureException $exception
     *
     * @return void
     */
    public function failed(ClusterWasCreated $event, Exception $exception)
    {
        $cluster = Cluster::find($event->clusterId);

        $cluster->state = Cluster::FAILED;
        $cluster->save();

        event(new ClusterHasFailed($cluster->id));
    }
}
