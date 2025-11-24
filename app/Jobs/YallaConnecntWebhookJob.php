<?php

namespace App\Jobs;

use App\Webhooks\YallaConnectWebhook;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class YallaConnecntWebhookJob implements ShouldQueue
{
    use Queueable,Batchable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('product-process-webhook');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        app(YallaConnectWebhook::class)->notify();
    }
}
