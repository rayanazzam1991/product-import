<?php

namespace App\Providers;

use App\Contracts\FetchProductsServiceInterface;
use App\Enum\ProductSourceEnum;
use App\Services\FetchMockSupplierService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        $this->app->bind(FetchProductsServiceInterface::class, function ($app, $parameters) {
            $productApiSource = $parameters['source'];

            // Bind the service implementation based on the source string
            return match ($productApiSource) {
                ProductSourceEnum::MOCK_SUPPLIER->value => resolve(FetchMockSupplierService::class),
                default => throw new \Exception("Unsupported products api source: {$productApiSource}"),
            };
        });

        Paginator::useTailwind();
    }
}
