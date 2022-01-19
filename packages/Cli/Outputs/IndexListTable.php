<?php

declare(strict_types=1);

namespace Sigmie\Cli\Outputs;

use Sigmie\Cli\Contracts\OutputFormat;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

class IndexListTable implements OutputFormat
{
    protected array $headers = [
        'Name', 'Alias', 'Health', 'Docs Count', 'Primary shards', 'Replica shards', 'Size',
    ];

    public function __construct(
        protected array $indicesJson,
        protected array $aliasesJson
    ) {
    }

    public function output(OutputInterface $output): void
    {
        $aliases = [];
        foreach ($this->aliasesJson as $data) {
            $index = $data['index'];
            $alias = $data['alias'];

            if (isset($aliases[$index])) {
                $aliases[$index] = [...$aliases[$index], $alias];
                continue;
            }

            $aliases[$index] = [$alias];
        }

        $table = new Table($output);

        $rows = array_map(fn ($result) => [
            $result['index'],
            (isset($aliases[$result['index']])) ? implode(',', $aliases[$result['index']]) : '',
            '<fg='.$result['health'].'>'.$result['health'].'</>',
            $result['docs.count'],
            $result['pri'],
            $result['rep'],
            $result['store.size'],
        ], $this->indicesJson);

        $table->setHeaders($this->headers)->setRows($rows)->render();
    }
}
