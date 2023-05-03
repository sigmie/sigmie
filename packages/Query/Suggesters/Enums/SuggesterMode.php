<?php

declare(strict_types=1);

namespace Sigmie\Query\Suggesters\Enums;

enum SuggesterMode: string
{
    case Always = 'always';

    case Missing = 'missing';

    case Popular = 'popular';
}
