<?php

declare(strict_types=1);

namespace Sigmie\Query\Suggesters\Enums;

enum SuggesterSort: string
{
    case Score = 'score';

    case Frequency = 'frequency';
}
