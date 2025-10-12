<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Sigmie\Index\Analysis\Analysis;
use Sigmie\Index\Contracts\Analysis as AnalysisInterface;
use Sigmie\Index\Contracts\Settings as SettingsInterface;

class Settings implements SettingsInterface
{
    public readonly ?int $primaryShards;

    public readonly ?int $replicaShards;

    protected AnalysisInterface $analysis;

    protected string $defaultPipeline;

    public function __construct(
        ?int $primaryShards = null,
        ?int $replicaShards = null,
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

    public function defaultPipeline(string $name): self
    {
        $this->defaultPipeline = $name;

        return $this;
    }

    public function config(string $name, string $value): self
    {
        $this->configs[$name] = $value;

        return $this;
    }

    public function primaryShards(): ?int
    {
        return $this->primaryShards;
    }

    public function replicaShards(): ?int
    {
        return $this->replicaShards;
    }

    public static function fromRaw(array $raw): static
    {
        $settings = $raw['index'];

        $analysis = ($settings['analysis'] ?? false)
            ? Analysis::fromRaw($settings['analysis']) : new Analysis();

        return new static(
            (isset($settings['number_of_shards']) ? (int)$settings['number_of_shards'] : null),
            (isset($settings['number_of_replicas']) ? (int)$settings['number_of_replicas'] : null),
            $analysis
        );
    }

    public function toRaw(): array
    {
        $res = array_merge([
            'analysis' => $this->analysis()->toRaw(),
        ], $this->configs);

        if ($this->primaryShards) {
            $res['number_of_shards'] = $this->primaryShards;
        }

        if (!is_null($this->replicaShards)) {
            $res['number_of_replicas'] = $this->replicaShards;
        }

        if ($this->defaultPipeline ?? false) {
            $res['default_pipeline'] = $this->defaultPipeline;
        }

        return $res;
    }
}
