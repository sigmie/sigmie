<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Sigmie\Index\Analysis\Analysis;
use Sigmie\Index\Contracts\Analysis as AnalysisInterface;
use Sigmie\Index\Contracts\Settings as SettingsInterface;

class Settings implements SettingsInterface
{
    public readonly int $primaryShards;

    public readonly int $replicaShards;

    protected AnalysisInterface $analysis;

    public function __construct(
        int $primaryShards = 1,
        int $replicaShards = 2,
        AnalysisInterface $analysis = new Analysis(),
        protected array $configs = []
    ) {
        $this->analysis = $analysis;
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

    public function primaryShards(): int
    {
        return $this->primaryShards;
    }

    public function replicaShards(): int
    {
        return $this->replicaShards;
    }

    public static function fromRaw(array $raw): static
    {
        $settings = $raw['index'];

        $analysis = ($settings['analysis'] ?? false)
            ? Analysis::fromRaw($settings['analysis']) : new Analysis();

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
            'analysis' => $this->analysis()->toRaw(),
        ], $this->configs);
    }
}
