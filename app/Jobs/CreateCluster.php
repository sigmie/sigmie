<?php

namespace App\Jobs;

use App\Events\ClusterCreated;
use Illuminate\Support\Str;
use Sigmie\App\Core\Cluster;
use Illuminate\Bus\Queueable;
use Sigmie\App\Core\ClusterManager;
use Illuminate\Queue\SerializesModels;
use App\Factories\ClusterManagerFactory;
use Illuminate\Queue\InteractsWithQueue;
use App\Facades\Cluster as FacadesCluster;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CreateCluster implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Project identifier
     *
     * @var int
     */
    private int $projectId;

    /**
     * Provider credentials
     *
     * @var array
     */
    private array $spec;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $projectId, array $spec)
    {
        $this->spec = $spec;
        $this->projectId = $projectId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $cluster = new Cluster();
        $cluster->setName($this->spec['name']);

        if ($this->creds['dataCenter'] === 'europe') {
            $cluster->setRegion('europe-west1');
            $cluster->setZone('europe-west1-b');
        }

        if ($this->creds['dataCenter'] === 'asia') {
            $cluster->setRegion('asia-northeast1');
            $cluster->setZone('asia-northeast1-b');
        }

        if ($this->creds['dataCenter'] === 'america') {
            $cluster->setRegion('us-west2');
            $cluster->setZone('us-west2-a');
        }

        $cluster->setDiskSize(15);
        $cluster->setNodesCount($this->spec['nodes']);

        $cluster->setUsername($this->spec['username']);
        $cluster->setPassword($this->spec['password']);

        $manager = (new ClusterManagerFactory)->create($this->projectId);
        $manager->create($cluster);

        event(new ClusterCreated($cluster));
    }
}
