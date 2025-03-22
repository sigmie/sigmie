<?php

declare(strict_types=1);

namespace Sigmie\Enums;

enum VectorStrategy: string
{
    // Low accuracy
    // Good for short strings
    case Concatenate = 'concatenate';

    // Average accuracy
    // Good for long strings
    case Average = 'average';

    // Max accuracy
    // Good for short strings
    case ScriptScore = 'script_score';
}
