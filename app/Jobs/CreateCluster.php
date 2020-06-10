<?php

namespace App\Jobs;

use App\Facades\Cluster as FacadesCluster;
use App\Factories\ClusterManagerFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Sigmie\App\Core\Cluster;
use Sigmie\App\Core\ClusterManager;

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
    public function __construct($projectId, $cluster)
    {
        $this->factory = new ClusterManagerFactory;
        $this->cluster = $cluster;
        $this->projectId = $projectId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $manager = $this->factory->create($this->projectId);
        $manager->create($this->cluster);
    }
}
