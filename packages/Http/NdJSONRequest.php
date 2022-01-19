<?php

declare(strict_types=1);

namespace Sigmie\Http;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\UriInterface as Uri;
use Sigmie\Http\Contracts\JSONRequest as JSONRequestInterface;

class NdJSONRequest extends Request implements JSONRequestInterface
{
    public const SEPARATOR = PHP_EOL;

    protected string|null $body;

    protected array $headers = [
        'Content-type' => 'application/x-ndjson',
    ];

    public function __construct(string $method, Uri $uri, ?array $body)
    {
        $this->body = is_null($body) ? $body : $this->encode($body);

        parent::__construct($method, $uri, $this->headers, $this->body);
    }

    public function body(): ?array
    {
        return $this->body ? $this->decode($this->body) : null;
    }

    public static function decode(string $ndjson): array
    {
        return array_map(function ($json) {
            return json_decode($json, true);
        }, self::split($ndjson));
    }

    public static function encode(array $data): string
    {
        $ndjson = '';
        array_walk($data, function ($item) use (&$ndjson) {
            $ndjson .= json_encode($item).self::SEPARATOR;
        });
        return $ndjson;
    }

    private static function split(string $ndjson)
    {
        return explode(self::SEPARATOR, $ndjson);
    }
}
