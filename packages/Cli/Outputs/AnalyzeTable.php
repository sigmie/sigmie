<?php

declare(strict_types=1);

namespace Sigmie\Cli\Outputs;

use Sigmie\Cli\Contracts\OutputFormat;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

class AnalyzeTable implements OutputFormat
{
    protected array $json;

    public function __construct(array $json)
    {
        $this->json = $json;
    }

    public function output(OutputInterface $output): void
    {
        $table = new Table($output);

        $data = $this->json['tokens'];

        $headers = ['token', 'start_offset', 'end_offset', 'type', 'position'];
        $table->setHeaders([
            $headers
        ]);

        foreach ($data as $row) {
            $table->addRow($row);
        }

        $table->render();
    }
}
