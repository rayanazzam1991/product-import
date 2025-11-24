<?php

namespace App\Listeners;

use App\Events\ProductFetchedEvent;
use App\Services\ProductSyncService;
use Illuminate\Support\Facades\Log;
use Throwable;

readonly class ProductFetched
{
    /**
     * Create the event listener.
     */
    public function __construct(private ProductSyncService $productSyncService)
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ProductFetchedEvent $event): void
    {
        Log::info('ProductFetched');
        $this->productSyncService->storeDataIntoStagingTable($event->productsListData);
    }

    /**
     * Handle a job failure.
     */
    public function failed(ProductFetchedEvent $event, Throwable $exception): void
    {
        Log::info('Failed Fetch', [$exception->getMessage()]);
    }
}
