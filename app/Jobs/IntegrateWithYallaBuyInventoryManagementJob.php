<?php

namespace App\Jobs;

use App\Services\IntegrateWithYallaBuyInventoryManagement;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IntegrateWithYallaBuyInventoryManagementJob implements ShouldQueue
{
    use Queueable,Batchable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('product-process-inventory');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        app(IntegrateWithYallaBuyInventoryManagement::class)->integrate();
    }
}
