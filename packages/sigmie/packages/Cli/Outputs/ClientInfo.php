<?php

declare(strict_types=1);

namespace Sigmie\Cli\Outputs;

use Composer\InstalledVersions;
use Sigmie\Cli\Contracts\OutputFormat;
use Symfony\Component\Console\Output\OutputInterface;

class ClientInfo implements OutputFormat
{
    protected string $host;

    protected string $port;

    /**
     * Elasticseach version
     */
    protected string $version;

    public function __construct(string $host, string $port, string $version)
    {
        $this->host = $host;
        $this->port = $port;
        $this->version = $version;
    }

    public function output(OutputInterface $output): void
    {
        $cliVersion = InstalledVersions::getRootPackage()['version'];

        $lines = [
            '------------------------------------------------',
            "Sigmie Cli {$cliVersion}",
            '------------------------------------------------',
            "Elasticsearch Version:  {$this->version}",
            "Elasticsearch Host:     {$this->host}",
            "Elasticsearch Port:     {$this->port}",
            '',
            '',
        ];

        foreach ($lines as $line) {
            $output->writeln("<fg=green>{$line}</>");
        }
    }
}
