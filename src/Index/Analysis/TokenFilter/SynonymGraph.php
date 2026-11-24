<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\TokenFilter;

class SynonymGraph extends Synonyms
{
    public function type(): string
    {
        return 'synonym_graph';
    }
}
