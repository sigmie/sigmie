<?php

declare(strict_types=1);

namespace Sigmie\Http;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Sigmie\Http\Contracts\JSONRequest as JSONRequestInterface;

class NdJSONRequest extends Request implements JSONRequestInterface
{
    protected array $headers = [
        'Content-type' => 'application/x-ndjson',
    ];

    public function __construct(string $method, Uri $uri, ?array $body)
    {
        $body = is_null($body) ? $body : $this->ndJsonEncode($body);

        parent::__construct($method, $uri, $this->headers, $body);
    }

    private function ndJsonEncode(array $values): string
    {
        $result = '';
        foreach ($values as $value) {
            $json = json_encode($value, 0);
            $result .= "{$json}\n";
        }

        return $result;
    }
}
