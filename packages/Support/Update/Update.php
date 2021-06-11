<?php


declare(strict_types=1);

namespace Sigmie\Support\Update;

use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Base\Contracts\Mappings as ContractsMappings;
use Sigmie\Support\Analysis\AnalyzerUpdate;
use Sigmie\Support\Analysis\Tokenizer\Builder as TokenizerBuilder;
use Sigmie\Support\Shared\Mappings;

class Update
{
    use Mappings;

    protected int $replicas = 2;

    protected int $shards = 1;

    public function __construct(protected DefaultAnalyzer $defaultAnalyzer)
    {
    }

    // public function analyzer(string $name)
    // {
    //     return new AnalyzerUpdate($this, $name);
    // }

    // public function defaultAnalyzer()
    // {
    //     return new AnalyzerUpdate($this, 'default');
    // }

    public function shards(int $shards)
    {
        $this->shards = $shards;

        return $this;
    }

    public function replicas(int $replicas)
    {
        $this->replicas = $replicas;

        return $this;
    }

    public function mappingsValue(): ContractsMappings
    {
        return $this->createMappings($this->defaultAnalyzer);
    }

    public function toRaw()
    {
        $mappings = $this->createMappings($this->defaultAnalyzer);

        return [
            'settings' => [
                'number_of_shards' => $this->shards,
                'number_of_replicas' => $this->replicas,
            ],
            'mappings' => $mappings->toRaw()
        ];
    }
}
