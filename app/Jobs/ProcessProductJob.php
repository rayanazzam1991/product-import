<?php

namespace App\Jobs;

use App\Models\StagingProduct;
use App\Services\ProductSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

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
        $stagingProducts = StagingProduct::query()->get();
        Log::info("start product processing");
        $stagingProducts->each(function ($staging) {
            if($staging->status == 'processing'){
                Log::info("End product processing");
                if ($staging->processed_products >= $staging->total_products) {
                    $staging->update([
                        'status' => 'done',
                        'finished_at' => now()
                    ]);
                }
            }
            if ($staging->status == 'pending'){
                Log::info('stagingBatch', [$this->stagingBatch]);
                app(ProductSyncService::class)->syncFromExternalApi($staging->raw_payload, $this->stagingBatch);
            }

        });
    }
}
