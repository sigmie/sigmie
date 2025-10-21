<?php

declare(strict_types=1);

namespace Sigmie\Enums;

enum CohereInputType: string
{
    case SearchDocument = 'search_document';

    case SearchQuery = 'search_query';

    case Classification = 'classification';

    case Clustering = 'clustering';
}
