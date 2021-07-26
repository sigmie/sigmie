<?php

declare(strict_types=1);

namespace Sigmie\Cli\Contracts;

use Symfony\Component\Console\Output\OutputInterface;

interface OutputFormat
{
    public function output(OutputInterface $outputInterface): void;
}
