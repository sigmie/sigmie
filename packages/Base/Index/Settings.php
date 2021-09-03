<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\Analysis\Analysis;
use Sigmie\Base\Contracts\Analysis as AnalysisInterface;
use Sigmie\Base\Contracts\Raw;

class Settings implements Raw
{
    public int $primaryShards;

    public int $replicaShards;

    protected AnalysisInterface $analysis;

    public function __construct(
        int $primaryShards = 1,
        int $replicaShards = 2,
        AnalysisInterface $analysis = null,
        protected array $configs = []
    ) {
        $this->analysis = $analysis ?: new Analysis();
        $this->primaryShards = $primaryShards;
        $this->replicaShards = $replicaShards;
    }

    public function analysis(): AnalysisInterface
    {
        return $this->analysis;
    }

    public function config(string $name, string $value): self
    {
        $this->configs[$name] = $value;

        return $this;
    }

    public function getPrimaryShards(): int
    {
        return $this->primaryShards;
    }

    public function getReplicaShards(): int
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

        $analysis = Analysis::fromRaw($settings['analysis']);

        return new static(
            (int)$settings['number_of_shards'],
            (int)$settings['number_of_replicas'],
            $analysis
        );
    }

    public function toRaw(): array
    {
        return array_merge([
            'number_of_shards' => $this->primaryShards,
            'number_of_replicas' => $this->replicaShards,
            'analysis' => $this->analysis()->toRaw()
        ], $this->configs);
    }
}
