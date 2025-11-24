<?php

namespace App\Jobs;

use App\Models\StagingProduct;
use App\Services\ProductSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessProductJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $stagingBatch
    ) {
        $this->onQueue('product-process');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $productJson = StagingProduct::query()->find($this->stagingBatch);
        app(ProductSyncService::class)->syncFromExternalApi($productJson->products_json,$this->stagingBatch);
    }
}
