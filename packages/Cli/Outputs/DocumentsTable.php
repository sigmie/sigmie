<?php

declare(strict_types=1);

namespace Sigmie\Cli\Outputs;

use Sigmie\Cli\Contracts\OutputFormat;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Output\OutputInterface;

class DocumentsTable implements OutputFormat
{
    protected array $json;

    protected string $indexName;

    public function __construct(array $json, string $indexName)
    {
        $this->json = $json;
        $this->indexName = $indexName;
    }

    public function output(OutputInterface $output): void
    {
        $table = new Table($output);

        $headers = [];
        $docs = [];

        foreach ($this->json as $doc) {
            $headers = array_unique(array_merge(array_keys($doc['_source']), $headers));
            $docs[] = $doc['_source'];
        }

        foreach ($headers as $index => $value) {
            // $table->setColumnMaxWidth($index, 50);
        }

        //Truncate long text
        foreach ($docs as $docIndex => $doc) {
            foreach ($doc as $dataIndex => $data) {
                if (is_string($data) && strlen($data) > 50) {
                    $docs[$docIndex][$dataIndex] = substr($data, 0, 47) . '...';
                }
            }
        }

        $table->setHeaders([
            [new TableCell(
                'Index name ' . $this->indexName,
                [
                    'colspan' => count($headers),
                    'style' => new TableCellStyle([
                        'align' => 'center',
                        'fg' => 'red',
                        'bg' => 'green',
                        // or
                        'cellFormat' => '<info>%s</info>',
                    ])
                ]
            )],
            $headers
        ]);

        foreach ($docs as $doc) {
            $row = [];
            foreach ($headers as $header) {
                $docRow = (isset($doc[$header])) ? $doc[$header] : '-';

                if (is_string($docRow) || is_int($docRow)) {
                    $row[] = $docRow;
                } elseif (is_array($docRow)) {
                    $row[] = json_encode($docRow);
                }
            }

            $table->addRow($row);
        }


        $table->render();
    }
}
