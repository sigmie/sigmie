<?php

namespace App\Jobs;

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

    private $factory;

    private $cluster;

    private $projectId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($projectId)
    {
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
        $cluster->setName('awesome-' . Str::lower(Str::random(4)));
        $cluster->setRegion('europe-west1');
        $cluster->setZone('europe-west1-b');
        $cluster->setDiskSize(15);
        $cluster->setNodesCount(3);
        $cluster->setUsername('sigmie');
        $cluster->setPassword('core');

        $f = new ClusterManagerFactory;
        $mana = $f->create($this->projectId);
        $mana->create($cluster);
    }
}
