<?php

namespace App\Jobs;

use App\Contracts\FetchProductsServiceInterface;
use App\Events\ProductFetchedEvent;
use App\Models\StagingProduct;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchProductsJob
{
    use Queueable;

    public function __construct(public string $productSource

    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {


        $fetchProductService = app(FetchProductsServiceInterface::class, ['source' => $this->productSource]);

        $productFetchData = $fetchProductService->fetch();
        Log::info('FetchProductsJob');
        ProductFetchedEvent::dispatch(json_encode($productFetchData));

    }
}
