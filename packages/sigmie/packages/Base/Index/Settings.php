<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

class Settings
{
    public int $primaryShards;

    public int $replicaShards;

    public function __construct($primaryShards = 1, $replicaShards = 2)
    {
        $this->primaryShards = $primaryShards;
        $this->replicaShards = $replicaShards;
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
}
