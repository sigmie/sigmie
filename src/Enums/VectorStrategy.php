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

    case ScriptScoreMin = 'script_score_min';

    case ScriptScoreMax = 'script_score_max';

    case ScriptScoreAvg = 'script_score_avg';

    case ScriptScoreSum = 'script_score_sum';

    case ScriptScoreMedian = 'script_score_median';
}
