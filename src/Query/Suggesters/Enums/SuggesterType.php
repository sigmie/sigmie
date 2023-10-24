<?php

declare(strict_types=1);

namespace Sigmie\Query\Suggesters\Enums;

enum SuggesterType: string
{
    case Term = 'term';

    case Phrase = 'phrase';

    case Completion = 'completion';
}
