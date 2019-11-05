<?php

namespace Sigma;

use Sigma\Contract\BootableResponse;
use Sigma\Contract\Response;
use Sigma\Contract\ResponseHandler as ResponseHandlerInterface;

class ResponseHandler implements ResponseHandlerInterface
{
    /**
     * Action dispatcher
     *
     * @var ActionDispatcher
     */
    private $actionDispatcher;

    public function __construct(ActionDispatcher $actionDispatcher)
    {
        $this->actionDispatcher = $actionDispatcher;
    }

    /**
     * Raw response handler method
     *
     * @param array $content
     * @param Response $response
     *
     * @return void
     */
    public function handle(array $content, Response $response)
    {
        if ($response instanceof BootableResponse) {
            $response->boot($this->actionDispatcher, $this);

            $content = $response->prepare($content);
        }

        return $response->result($content);
    }
}
