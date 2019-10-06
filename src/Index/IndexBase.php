<?php

namespace Ni\Elastic\Index;

use Elasticsearch\Client as Elasticsearch;
use Elasticsearch\Endpoints\Cat\Repositories;
use JsonSerializable;
use Ni\Elastic\Exception\NotImplementedException;
use Ni\Elastic\Manager;
use Ni\Elastic\Response\Response;
use Ni\Elastic\Response\Factory;

abstract class IndexBase implements Manager
{
}
