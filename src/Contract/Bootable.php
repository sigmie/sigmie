<?php

namespace Sigma\Contract;

use Sigma\Contract\Action;
use Sigma\Contract\Response;

interface Bootable
{

    public function boot(ActionDispatcher $actionDispatcher, ResponseHandler $responseHandler);

    public function isBooted(): bool;

    public function execute($params, Action $action, Response $response);
}
