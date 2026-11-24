<?php

declare(strict_types=1);

namespace Sigmie\Index;

use InvalidArgumentException;
use Sigmie\Index\Analysis\Analysis;
use Sigmie\Index\Contracts\Analysis as AnalysisInterface;
use Sigmie\Index\Contracts\Settings as SettingsInterface;

use function Sigmie\Functions\object_map;

class Settings implements SettingsInterface
{
    public function __construct(public readonly ?int $primaryShards = null, public readonly ?int $replicaShards = null, protected AnalysisInterface $analysis = new Analysis, protected array $configs = []) {}

    public function analysis(): AnalysisInterface
    {
        $overrides = $this->analysisOverrides();

        return $overrides === [] ? $this->analysis : Analysis::fromRaw($this->effectiveAnalysis($overrides));
    }

    public function defaultPipeline(string $name): self
    {
        return $this->config('default_pipeline', $name);
    }

    public function config(string $name, string|array|bool|int|float $value): self
    {
        $key = $name === 'index.default_pipeline' ? 'default_pipeline' : $name;
        if ($key === 'default_pipeline') {
            unset($this->configs['index.default_pipeline']);
        }
        $this->configs[$key] = $value;

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
        $envelope = $raw['settings'] ?? array_diff_key($raw, [
            'aliases' => true,
            'mappings' => true,
            'index_patterns' => true,
            'order' => true,
        ]);
        $flat = array_filter($envelope, static fn (int|string $key): bool => str_starts_with((string) $key, 'index.'), ARRAY_FILTER_USE_KEY);
        $nested = dot(array_replace_recursive(
            array_diff_key($envelope, $flat, ['index' => true]),
            $envelope['index'] ?? [],
        ));
        foreach ($flat as $name => $value) {
            $nested->set(substr($name, 6), $value);
        }
        $settings = $nested->all();

        $analysis = Analysis::fromRaw($settings['analysis'] ?? []);

        $configs = $settings;
        unset(
            $configs['analysis'],
            $configs['number_of_shards'],
            $configs['number_of_replicas'],
            $configs['provided_name'],
            $configs['creation_date'],
            $configs['creation_date_string'],
            $configs['uuid'],
            $configs['version'],
        );

        return new static(
            (isset($settings['number_of_shards']) ? (int) $settings['number_of_shards'] : null),
            (isset($settings['number_of_replicas']) ? (int) $settings['number_of_replicas'] : null),
            $analysis,
            $configs,
        );
    }

    public function toRaw(): array
    {
        $overrides = $this->analysisOverrides();
        $res = array_diff_key($this->configs, $overrides);
        $res['analysis'] = array_map(object_map(...), $this->effectiveAnalysis($overrides));

        if ($this->primaryShards !== null) {
            $res['number_of_shards'] = $this->primaryShards;
        }

        if (! is_null($this->replicaShards)) {
            $res['number_of_replicas'] = $this->replicaShards;
        }

        return $res;
    }

    private function analysisOverrides(): array
    {
        return array_filter(
            $this->configs,
            static fn (int|string $name): bool => preg_match('/^(?:index\.)?analysis(?:\.|$)/D', (string) $name) === 1,
            ARRAY_FILTER_USE_KEY,
        );
    }

    private function effectiveAnalysis(array $overrides): array
    {
        $analysis = dot(array_map(static fn ($components): array => (array) $components, $this->analysis->toRaw()));
        foreach ($overrides as $name => $value) {
            $key = preg_replace('/^index\./', '', (string) $name);
            if ($key === 'analysis') {
                $value ??= [];
                if (! is_array($value)) {
                    throw new InvalidArgumentException('The analysis setting requires an array of named components.');
                }
                foreach ($value as $group => $components) {
                    $analysis->set($group, array_replace($analysis->get($group, []), (array) $components));
                }

                continue;
            }

            $path = substr($key, 9);
            $analysis->set($path, str_contains($path, '.') ? $value : array_replace($analysis->get($path, []), (array) $value));
        }

        return $analysis->all();
    }
}
