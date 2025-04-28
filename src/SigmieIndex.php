<?php

declare(strict_types=1);

namespace Sigmie;

use Sigmie\Document\AliveCollection;
use Sigmie\Index\NewIndex;
use Sigmie\Index\Shared\SigmieIndex as SharedSigmieIndex;
use Sigmie\Mappings\NewProperties;
use Sigmie\Search\NewSearch;
use Sigmie\Semantic\Contracts\AIProvider;
use Sigmie\Semantic\Providers\SigmieAI as SigmieEmbeddings;

abstract class SigmieIndex
{
    use SharedSigmieIndex;
}
