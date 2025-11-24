<?php

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RemoveOutdatedProducts implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly array $syncedIds = [])
    {
        $this->onQueue('product-remove-outdated');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Delete products that are NOT in the external source anymore
        Product::query()->whereNotIn('id', $this->syncedIds)->delete();
    }
}
