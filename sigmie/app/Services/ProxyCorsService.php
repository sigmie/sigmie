<?php

declare(strict_types=1);

namespace App\Services;

use Asm89\Stack\CorsService;

final class ProxyCorsService extends CorsService
{
    protected array $options;

    public function __construct()
    {
        parent::__construct([
            'allowedOrigins' => ['*'], // From every origin
            'allowedMethods' => ['*'], // All methods
            'allowedOriginsPatterns' => [],
            'supportsCredentials' => false, // Don't expose token in browser
            'allowedHeaders' => ['*'],
            'exposedHeaders' => ['*'],
            'maxAge' => 0, // Max preflight cache sec
        ]);
    }
}
