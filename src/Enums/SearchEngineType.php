<?php

declare(strict_types=1);

namespace Sigmie\Enums;

enum SearchEngineType: string
{
    case Elasticsearch = 'elasticsearch';

    case OpenSearch = 'opensearch';
}
