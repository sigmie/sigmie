<?php

declare(strict_types=1);

namespace Sigmie\Enums;

enum RecommendationStrategy: string
{
    case Centroid = 'centroid';

    case Fusion = 'fusion';
}
