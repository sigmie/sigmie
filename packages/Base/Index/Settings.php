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

    public function getPrimaryShards()
    {
        return $this->primaryShards;
    }

    public function getReplicaShards()
    {
        return $this->replicaShards;
    }

    public static function fromRaw(array $response)
    {
        $indexIdentifier = array_key_first($response);
        // $settings = $response[$indexIdentifier]['settings']['index'];
        // $mappings = $response[$indexIdentifier]['mappings'];

        // $mappings  = Mappings::fromRaw($response[$indexIdentifier]['mappings']);
        // $analysis = Analysis::fromRaw($settings['analysis']);

        // $settings = new Settings(
        //     $settings['number_of_shards'],
        //     $settings['number_of_replicas'],
        //     $analysis
        // );

        // return $settings;
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
