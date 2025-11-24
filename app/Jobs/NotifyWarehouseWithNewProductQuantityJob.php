<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class NotifyWarehouseWithNewProductQuantityJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('product-process-warehouse');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('NotifyWarehouseWithNewProductQuantityJob');
        usleep(2 * 1000 * 1000); // sleep for 2 seconds
    }
}
