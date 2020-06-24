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
     * Provider credentials
     *
     * @var array
     */
    private array $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $cluster = new Cluster();
        $cluster->setName($this->data['name']);

        if ($this->data['dataCenter'] === 'europe') {
            $cluster->setRegion('europe-west1');
            $cluster->setZone('europe-west1-b');
        }

        if ($this->data['dataCenter'] === 'asia') {
            $cluster->setRegion('asia-northeast1');
            $cluster->setZone('asia-northeast1-b');
        }

        if ($this->data['dataCenter'] === 'america') {
            $cluster->setRegion('us-west2');
            $cluster->setZone('us-west2-a');
        }

        $cluster->setDiskSize(15);
        $cluster->setNodesCount($this->data['nodes']);

        $cluster->setUsername($this->data['username']);
        $cluster->setPassword($this->data['password']);

        $manager = (new ClusterManagerFactory)->create($this->data['project_id']);
        $manager->create($cluster);

        event(new ClusterCreated($cluster));
    }
}
