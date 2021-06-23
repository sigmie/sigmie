<?php

declare(strict_types=1);

namespace Sigmie\Http;

use ArrayAccess;
use GuzzleHttp\Psr7\Response;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Sigmie\Http\Contracts\JSONResponse as JSONResponseInterface;

class JSONResponse implements ArrayAccess, JSONResponseInterface
{
    /**
     * PSR response.
     */
    protected ResponseInterface $response;

    protected $decoded;

    public function __construct(ResponseInterface $psrResponse)
    {
        $this->response = $psrResponse;
    }

    /**
     * Get the body of the response.
     *
     * @return string
     */
    public function __toString()
    {
        return json_encode($this->json());
    }

    /**
     * Get the body of the response.
     *
     * @return string
     */
    public function body()
    {
        return (string) $this->response->getBody();
    }

    public function json($key = null): int|bool|string|array|null
    {
        if (!$this->decoded) {
            $this->decoded = json_decode($this->body(), true);
        }

        if (is_null($key)) {
            return $this->decoded;
        }

        if (isset($this->decoded[$key])) {
            return $this->decoded[$key];
        }

        return null;
    }

    public function psr(): Response
    {
        return $this->response;
    }

    /**
     * Get a header from the response.
     *
     * @param  string  $header
     * @return string
     */
    public function header(string $header)
    {
        return $this->response->getHeaderLine($header);
    }

    public function failed(): bool
    {
        return $this->serverError() || $this->clientError();
    }

    public function clientError()
    {
        return $this->code() >= 400 && $this->code() < 500;
    }

    public function code(): int
    {
        return (int) $this->response->getStatusCode();
    }

    public function serverError()
    {
        return $this->code() >= 500;
    }

    /**
     * Determine if the given offset exists.
     *
     * @param  string  $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->json()[$offset]);
    }

    /**
     * Get the value for a given offset.
     *
     * @param  string  $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->json()[$offset];
    }

    /**
     * Set the value at the given offset.
     *
     * @param  string  $offset
     * @param  mixed  $value
     * @return void
     *
     * @throws LogicException
     */
    public function offsetSet($offset, $value)
    {
        throw new LogicException('Response data may not be mutated using array access.');
    }

    /**
     * Unset the value at the given offset.
     *
     * @param  string  $offset
     * @return void
     *
     * @throws LogicException
     */
    public function offsetUnset($offset)
    {
        throw new LogicException('Response data may not be mutated using array access.');
    }
}
