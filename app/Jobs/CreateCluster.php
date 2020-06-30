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
use Sigmie\App\Core\Cloud\Regions\America;
use Sigmie\App\Core\Cloud\Regions\Asia;
use Sigmie\App\Core\Cloud\Regions\Europe;

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
            $cluster->setRegion(new Europe);
        }

        if ($this->data['dataCenter'] === 'asia') {
            $cluster->setRegion(new Asia);
        }

        if ($this->data['dataCenter'] === 'america') {
            $cluster->setRegion(new America);
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
