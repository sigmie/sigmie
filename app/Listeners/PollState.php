<?php declare(strict_types=1);

namespace App\Listeners;

use App\Events\ClusterHasFailed;
use App\Events\ClusterWasBooted;
use App\Events\ClusterWasCreated;
use App\Models\Cluster;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class PollState implements ShouldQueue
{
    public $tries = 30;

    public $retryAfter = 15;

    public $delay = 90;

    public function handle(ClusterWasCreated $event): void
    {
        $domain = config('services.cloudflare.domain');

        $cluster = Cluster::find($event->clusterId);

        $password = decrypt($cluster->password);

        /** @var  Response */
        $response = Http::withBasicAuth($cluster->username, $password)->timeout(3)->get("https://{$cluster->name}.{$domain}");

        if ($response->getStatusCode() === 200) {

            $cluster->state = Cluster::RUNNING;
            $cluster->save();

            event(new ClusterWasBooted($cluster->id));

            return;
        }

        throw new \Exception("Cluster run check failed after {$this->tries} tries with {$this->retryAfter} delay between each of them.");
    }

    public function failed(ClusterWasCreated $event, Exception $exception): void
    {
        $cluster = Cluster::find($event->clusterId);

        $cluster->state = Cluster::FAILED;
        $cluster->save();

        event(new ClusterHasFailed($cluster->id));
    }
}
