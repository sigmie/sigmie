<?php

namespace App\Listeners;

use App\Cluster;
use App\Events\ClusterCreated;
use App\Events\ClusterHasFailed;
use App\Events\ClusterIsRunning;
use App\Events\ClusterWasCreated;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;

class AwaitElasticsearchBoot
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(ClusterWasCreated $event)
    {
        $maxAttempts = 60;
        $attempts = 0;
        $delaySeconds = 15;
        $booted = false;
        $domain = config('services.cloudflare.domain');

        $cluster = Cluster::find($event->clusterId);

        while ($booted === false && $attempts <= $maxAttempts) {

            $password = decrypt($cluster->password);

            /** @var  Response */
            $response = Http::withBasicAuth($cluster->username, $password)->get("https://{$cluster->name}.{$domain}");

            $booted = $response->getStatusCode() === 200;

            if ($booted) {
                break;
            }

            sleep($delaySeconds);
        }

        if ($booted == true) {
            $cluster->state === Cluster::RUNNING;
            $cluster->save();

            event(new ClusterIsRunning($cluster->id));
        }

        if ($booted === false) {

            $cluster->state === Cluster::FAILED;
            $cluster->save();

            event(new ClusterHasFailed($cluster->id));
        }
    }
}
