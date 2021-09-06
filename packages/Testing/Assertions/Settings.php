<?php

declare(strict_types=1);

namespace Sigmie\Testing\Assertions;

trait Settings
{
    use Contracts;

    private string $name;

    private array $data;

    public function assertShards(int $number): void
    {
        $this->assertEquals(
            (string) $number,
            $this->data['settings']['index']['number_of_shards'],
            "Failed to assert that index {$this->name} has '{$number}' shards."
        );
    }

    public function assertReplicas(int $number): void
    {
        $this->assertEquals(
            (string) $number,
            $this->data['settings']['index']['number_of_replicas'],
            "Failed to assert that index {$this->name} has '{$number}' replicas."
        );
    }
}
