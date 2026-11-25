<?php

declare(strict_types=1);

namespace Sigmie\Base\Http;

use Exception;
use JsonException;
use Psr\Http\Message\StreamInterface;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse as ElasticsearchResponseInterface;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Http\JSONResponse;
use stdClass;

use function Sigmie\Functions\response_value;

class ElasticsearchResponse extends JSONResponse implements ElasticsearchResponseInterface
{
    private ?string $streamedBody = null;

    public function body(): string
    {
        return $this->getBody()->isSeekable() ? parent::body() : ($this->streamedBody ??= parent::body());
    }

    public function __clone(): void
    {
        if (isset($this->decoded)) {
            $this->decoded = clone $this->decoded;
        }
    }

    public function withBody(StreamInterface $body): static
    {
        return $body === $this->getBody() ? $this : static::fromPsrResponse(parent::withBody($body));
    }

    public function json(null|int|string $key = null): int|bool|string|array|null|float
    {
        $this->decoded ??= dot($this->decodeBody());
        $values = $this->decoded->all();

        $value = $key === null ? $values : response_value($values, (string) $key);

        return $value instanceof stdClass ? (array) $value : $value;
    }

    private function decodeBody(): mixed
    {
        $body = $this->body();
        if ($body === '') {
            return null;
        }

        if (strtolower($this->getHeaderLine('Content-Encoding')) === 'gzip') {
            $body = @gzdecode($body);
            if ($body === false) {
                throw new ElasticsearchException(['type' => 'invalid_gzip_response'], $this->code());
            }
        }

        try {
            if (str_contains($body, '"_source"')) {
                return $this->decodeNode(json_decode($body, flags: JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR));
            }

            return json_decode($body, true, flags: JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            if ($this->code() >= 200 && $this->code() < 300
                && stripos($this->getHeaderLine('Content-Type'), 'json') !== false) {
                throw new ElasticsearchException(['type' => 'invalid_json_response', 'reason' => $jsonException->getMessage()], $this->code());
            }

            return null;
        }
    }

    private function decodeNode(mixed $node, bool $inSource = false): mixed
    {
        if (! is_array($node) && ! $node instanceof stdClass) {
            return $node;
        }

        $values = (array) $node;
        $document = ! $inSource && (isset($values['_id']) || isset($values['_nested']));
        foreach ($values as $key => $value) {
            // Document exposes the source root as an array. Inside it, {} and {"0":...}
            // need an object to distinguish them from [] and [...] on the next write.
            $values[$key] = $document && $key === '_source' && $value instanceof stdClass
                ? (array) $this->decodeNode($value, true)
                : $this->decodeNode($value, $inSource);
        }

        return $inSource && $node instanceof stdClass && array_is_list($values) ? (object) $values : $values;
    }

    public function failed(): bool
    {
        if ($this->code() < 200 || $this->code() >= 300) {
            return true;
        }

        return $this->hasErrorKey();
    }

    public function exception(ElasticsearchRequest $request): Exception
    {
        $type = sprintf('Request failed with code %d.', $this->code());

        if (is_string($this->json('error'))) {
            $type = $this->json('error');
        }

        if (is_string($this->json('error.type'))) {
            $type = $this->json('error.type');
        }

        if (is_string($this->json('error.caused_by.type'))) {
            $type = $this->json('error.caused_by.type');
        }

        if (is_string($this->json('error.root_cause.0.type'))) {
            $type = $this->json('error.root_cause.0.type');
        }

        if (is_string($this->json('_shards.failures.0.reason.type'))) {
            $type = $this->json('_shards.failures.0.reason.type');
        }

        if (is_string($this->json('failures.0.cause.type'))) {
            $type = $this->json('failures.0.cause.type');
        }

        $body = $request->getBody();

        $position = $body->isSeekable() ? $body->tell() : null;
        if ($position !== null) {
            $body->rewind();
        }

        try {
            $contents = $body->getContents();
        } finally {
            if ($position !== null) {
                $body->seek($position);
            }
        }

        return new ElasticsearchException([
            'type' => $type,
            'code' => $this->code(),
            'json' => $this->json(),
            'body' => $this->body(),
            'request' => $this->requestBody($contents, stripos($request->getHeaderLine('Content-Type'), 'ndjson') !== false),
        ], $this->code());
    }

    private function requestBody(string $contents, bool $ndjson): mixed
    {
        if (! $ndjson) {
            $decoded = json_decode($contents, true, flags: JSON_BIGINT_AS_STRING);
            if ($decoded !== null || $contents === '' || $contents === 'null') {
                return $decoded;
            }
        }

        $operations = [];

        foreach (preg_split("/\r\n|\n|\r/", $contents) as $line) {
            if ($line === '') {
                continue;
            }

            $operations[] = json_decode($line, true, flags: JSON_BIGINT_AS_STRING);
        }

        return $operations;
    }

    private function hasErrorKey(): bool
    {
        return is_string($this->json('error')) || is_string($this->json('error.type'));
    }
}
