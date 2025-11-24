<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyCustomerBackToStockJob implements ShouldQueue
{
    use Queueable,Batchable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('product-process-notify-customer');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $zero = 0;
       $var = 100/$zero;
        usleep(2 * 1000 * 1000); // sleep for 2 seconds
    }
}
