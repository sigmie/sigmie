<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\Contracts\RawRepresentation;

class Settings implements RawRepresentation
{
    public int $primaryShards;

    public int $replicaShards;

    public ?Analysis $analysis;

    public function __construct(
        int $primaryShards = 1,
        int $replicaShards = 2,
        Analysis $analysis = null
    ) {
        $this->analysis = $analysis;
        $this->primaryShards = $primaryShards;
        $this->replicaShards = $replicaShards;
    }

    public function getPrimaryShards()
    {
        return $this->primaryShards;
    }

    public function getReplicaShards()
    {
        return $this->replicaShards;
    }

    public static function fromRaw(array $response): static
    {
        $indexIdentifier = array_key_first($response);

        if (isset($response['settings']) === false) {
            $settings = $response[$indexIdentifier]['settings']['index'];
        } else {
            $settings = $response['settings']['index'];
        }

        $defaultAnalyzerName = $response['mappings']['dynamic_templates'][0]['sigmie']['mapping']['analyzer'];
        $analysis = Analysis::fromRaw($settings['analysis'], $defaultAnalyzerName);

        return new Settings(
            (int)$settings['number_of_shards'],
            (int)$settings['number_of_replicas'],
            $analysis
        );
    }

    public function toRaw(): array
    {
        return [
            'number_of_shards' => $this->primaryShards,
            'number_of_replicas' => $this->replicaShards,
            'analysis' => $this->analysis->toRaw()
        ];
    }
}
