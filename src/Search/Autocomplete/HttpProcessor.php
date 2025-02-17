<?php

declare(strict_types=1);

namespace Sigmie\Search\Autocomplete;

class HttpProcessor extends Processor
{
    protected function type(): string
    {
        return 'script';
    }

    protected function values(): array
    {
        return [
            "lang" => "painless",
            "source" => "
                if (ctx.httpClient != null) {
                    def response = ctx.httpClient.execute('POST', 'https://app.sigmie.com/embeddings', [
                        'Content-Type': 'application/json'
                    ], ['text': ctx.text]);
                    ctx._source.embedding2 = response;
                } else {
                    throw new NullPointerException('httpClient is null');
                }
            ",
            // "params" => [
            //     // "text" => "{{text}}"
            // ]
        ];
    }
}
