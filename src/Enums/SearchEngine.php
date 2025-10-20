<?php

declare(strict_types=1);

namespace Sigmie\Enums;

enum SearchEngine: string
{
    case Elasticsearch = 'elasticsearch';

    case OpenSearch = 'opensearch';
}
