<?php

declare(strict_types=1);

namespace Sigmie\Cli\Commands;

use Sigmie\Cli\BaseCommand;
use Sigmie\Cli\Outputs\IndexListTable;

class IndexList extends BaseCommand
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'index:list';

    public function executeCommand(): int
    {
        $raw = $this->client->http->get('_cat/indices?format=json')->getBody()->getContents();

        $json = json_decode($raw, true);

        $this->output(new IndexListTable($json));

        return 1;
    }

    protected function configure()
    {
        parent::configure();
    }
}
