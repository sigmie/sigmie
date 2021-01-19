<?php

declare(strict_types=1);

namespace Sigmie\Cli\Outputs;

use Sigmie\Cli\Contracts\OutputFormat;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

class IndexListTable implements OutputFormat
{
    protected array $json;

    protected array $headers = [
        'Index name', 'Health', 'Docs Count', 'Primary shards', 'Replica shards', 'Size',
    ];

    public function __construct(array $json)
    {
        $this->json = $json;
    }

    public function output(OutputInterface $output): void
    {
        $table = new Table($output);

        $rows = array_map(fn ($result) => [
            $result['index'],
            '<fg=' . $result['health'] . '>' . $result['health'] . '</>',
            $result['docs.count'],
            $result['pri'],
            $result['rep'],
            $result['store.size'],
        ], $this->json);

        $table->setHeaders($this->headers)->setRows($rows)->render();
    }
}
