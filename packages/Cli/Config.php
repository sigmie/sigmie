<?php

declare(strict_types=1);

namespace Sigmie\Cli;

class Config
{
    protected string $filePath;

    protected array $config;

    protected array $defaultConf = [
        'active' => 'local',
        'clusters' => [
            'local' => [
                'host' => '127.0.0.1',
                'port' => '9200',
                'auth' => null
            ]
        ]
    ];

    public function __construct()
    {
        $this->createConfigFileIfNotExists();
        $this->config = $this->getConfig();
    }

    public function getActiveCluster()
    {
        $key = $this->config['active'];

        return $this->config['clusters'][$key];
    }

    public function getCluster(string $key): array
    {
        return $this->config['clusters'][$key];
    }

    public function getConfig(): array
    {
        $content = file_get_contents($this->filePath);
        return json_decode($content, true);
    }

    protected function createConfigFileIfNotExists()
    {
        $home = getenv('HOME');
        $homePath = "{$home}/.sigmie";
        $this->filePath = "{$homePath}/config.json";

        if (is_dir($homePath) === false) {
            mkdir($homePath);
            $this->initDefaultConfig();
        }

        if (file_exists($this->filePath) === false) {
            touch($this->filePath);
            $this->initDefaultConfig($this->filePath);
        };
    }

    private function initDefaultConfig(): void
    {
        file_put_contents($this->filePath, json_encode($this->defaultConf, JSON_PRETTY_PRINT));
    }
}
