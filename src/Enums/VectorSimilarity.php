<?php

declare(strict_types=1);

namespace Sigmie\Enums;

enum VectorSimilarity: string
{
    case Cosine = 'cosine';

    case Euclidean = 'l2_norm';

    case DotProduct = 'dot_product';

    case MaxInnerProduct = 'max_inner_product';
}
