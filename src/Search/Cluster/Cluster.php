<?php

declare(strict_types=1);

namespace Sigmie\Search\Cluster;

use Sigmie\Contracts\Entity;

class Cluster implements Entity
{
    public string $health;

    public int $nodesCount;

    public string $name;

    public function __construct(array $data)
    {
        $this->health = $data['status'];
        $this->nodesCount = (int) $data['number_of_nodes'];
        $this->name = $data['cluster_name'];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNodesCount(): int
    {
        return $this->nodesCount;
    }

    public function getHealth(): string
    {
        return $this->health;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setNodesCount(int $count): self
    {
        $this->nodesCount = $count;

        return $this;
    }

    public function setHealth(string $health): self
    {
        $this->health = $health;

        return $this;
    }
}
