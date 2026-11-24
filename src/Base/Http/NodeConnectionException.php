<?php

declare(strict_types=1);

namespace Sigmie\Base\Http;

use Elastic\Transport\Exception\NoNodeAvailableException;
use Http\Client\Exception as HttpClientException;

// The Guzzle HTTPlug adapter wraps exceptions that lack the client exception interface.
final class NodeConnectionException extends NoNodeAvailableException implements HttpClientException {}
