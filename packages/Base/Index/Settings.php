<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

class Settings
{
    public int $primaryShards;

    public int $replicaShards;

    public Analysis $analysis;

    public function __construct(
        $primaryShards = 1,
        $replicaShards = 2,
        Analysis $analysis = null
    ) {
        if ($analysis === null) {
            $analysis = new Analysis();
        }

        $this->analysis = $analysis;
        $this->primaryShards = $primaryShards;
        $this->replicaShards = $replicaShards;
    }

    public function getAnalysis(): Analysis
    {
        return $this->analysis;
    }

    public function setPrimaryShards(int $number): self
    {
        $this->primaryShards = $number;

        return $this;
    }

    public function setReplicaShards(int $number): self
    {
        $this->replicaShards = $number;

        return $this;
    }

    public function getPrimaryShards()
    {
        return $this->primaryShards;
    }

    public function getReplicaShards()
    {
        return $this->replicaShards;
    }

    public static function fromResponse(array $response)
    {
        $shards = (int) $response['settings']['index']['number_of_shards'];
        $replicas = (int) $response['settings']['index']['number_of_replicas'];


        return new static($shards, $replicas);
    }

    public function raw()
    {
        return [
            'number_of_shards' => $this->primaryShards,
            'number_of_replicas' => $this->replicaShards,
            'analysis' => $this->analysis->raw()
        ];
    }
}
