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
            sprintf("Failed to assert that index %s has '%d' shards.", $this->name, $number)
        );
    }

    public function assertReplicas(int $number): void
    {
        $this->assertEquals(
            (string) $number,
            $this->data['settings']['index']['number_of_replicas'],
            sprintf("Failed to assert that index %s has '%d' replicas.", $this->name, $number)
        );
    }
}
