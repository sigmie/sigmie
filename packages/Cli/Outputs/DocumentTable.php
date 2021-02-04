<?php

declare(strict_types=1);

namespace Sigmie\Cli\Outputs;

use Sigmie\Cli\Contracts\OutputFormat;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Output\OutputInterface;

class DocumentTable implements OutputFormat
{
    protected array $json;

    public function __construct(array $json)
    {
        $this->json = $json['docs'][0];
    }

    public function output(OutputInterface $output): void
    {
        $table = new Table($output);

        $data = $this->json['_source'];

        $table->setHeaders([
            [new TableCell(
                'Index name: ' . $this->json['_index'],
                [
                    'colspan' => count($data),
                    'style' => new TableCellStyle([
                        'align' => 'center',
                        'fg' => 'red',
                        'bg' => 'green',
                        // or
                        'cellFormat' => '<info>%s</info>',
                    ])
                ]
            )],
            array_keys($data)
        ]);

        $table->addRow(array_values($data));

        $table->render();
    }
}
