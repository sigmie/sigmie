<?php

declare(strict_types=1);

namespace App\Jobs\Proxy;

use App\Services\ElasticsearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SaveProxyRequest implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $request;

    private array $response;

    public function __construct(
        array $data,
        private int $clusterId
    ) {
        $this->request = $data['request'];
        $this->response = $data['response'];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(ElasticsearchService $elasticsearch)
    {
        $elasticsearch->add([
            'cluster' => $this->clusterId,
            'request' => $this->request,
            'response' => $this->response,
        ]);
    }
}
