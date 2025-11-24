<?php

namespace App\Services;

use App\DTOs\NormalizedProductDTO;
use App\Events\JobProcessing;
use App\Jobs\IntegrateWithYallaBuyInventoryManagementJob;
use App\Jobs\NotifyCustomerBackToStockJob;
use App\Jobs\ProcessProductJob;
use App\Jobs\RemoveOutdatedProducts;
use App\Jobs\YallaConnecntWebhookJob;
use App\Models\Product;
use App\Models\StagingProduct;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

readonly class ProductSyncService
{
    public function __construct(
        private ProductRelationSyncService $productRelationSyncService
    ) {}

    public function storeDataIntoStagingTable(string $externalProductsJson): void
    {
        $stagingBatch = StagingProduct::query()->create([
            'products_json' => $externalProductsJson,
        ]);

        ProcessProductJob::dispatch($stagingBatch->id);

    }

    public function syncFromExternalApi(string $externalProductsJson, int $stagingProductId): array
    {

        $externalProducts = Json::decode($externalProductsJson);
        $overallStart = now();
        $start = Carbon::now();
        $syncedIds = [];
        $allJobs = [];

        $stagingProduct = StagingProduct::query()->find($stagingProductId);

        DB::beginTransaction();

        try {
            foreach ($externalProducts as $rawProduct) {
                $dto = $this->normalizeExternalProduct($rawProduct);

                // Remember that this product was synced
                $syncedIds[] = $dto->id;

                // Insert/Update product row
                $dbRow = $dto->toDatabaseRow();
                $dbRow['created_at'] = $start;
                $dbRow['updated_at'] = $start;

                Product::updateOrCreate(
                    ['id' => $dto->id],
                    $dbRow
                );

                // Fetch the model and sync relations (same logic used by CSV import)
                $product = Product::find($dto->id);
                $this->productRelationSyncService->sync($product, $dto->variations, $dto->warehouses);
                $allJobs[] = [
                    new NotifyCustomerBackToStockJob(),
                    new YallaConnecntWebhookJob(),
                    new IntegrateWithYallaBuyInventoryManagementJob(),
                ];

            }

            DB::commit();
            // Create a batch for this product
            Log::info("allJobs",$allJobs);
            $batchStart = now();
            Bus::batch($allJobs)
                ->name("product-{$product->id}-sync")
                ->then(function (Batch $batch) use ($product, $batchStart,$stagingProduct) {
                    $duration = $batchStart->diffInSeconds(now());
                    Log::info("Product {$product->id} full async processing took {$duration} seconds");
                    // Optional: save to product record
                    $stagingProduct->update([
                        'async_processed_in' => $duration,
                        'status'=>'done'
                    ]);
                })
                ->catch(function (Batch $batch, \Throwable $e) use ($product) {
                    Log::error("Batch {$batch->id} failed for product {$product->id}", [
                        'error' => $e->getMessage(),
                    ]);
                })
                ->dispatch();

            RemoveOutdatedProducts::dispatch($syncedIds);

            DB::commit();
            $overallDuration = $overallStart->diffInSeconds(now());
            Log::info("ALL PRODUCTS SYNC started at {$overallStart}, took {$overallDuration} seconds in dispatching batches");

            return [
                'success' => true,
                'synced' => count($syncedIds),
                'dispatch_duration_seconds' => $overallDuration,
            ];

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('syncFromExternalApi error', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function normalizeExternalProduct(array $raw): NormalizedProductDTO
    {

        // Handle deletion
        $status = array_key_exists('isDeleted', $raw) && $raw['isDeleted'] ? 'deleted' : 'active';

        // SKU: Generate it from name + product id
        $skuBase = strtoupper(str_replace(' ', '-', $raw['name'])).'-'.$raw['id'];

        // Extract attributes from variations
        $colorValues = [];
        $materialValues = [];

        foreach ($raw['variations'] as $v) {
            $colorValues[] = $v['color'];
            $materialValues[] = $v['material'];
        }

        $colorValues = array_values(array_unique($colorValues));
        $materialValues = array_values(array_unique($materialValues));

        // Build variations
        $normalizedVariations = [];
        foreach ($raw['variations'] as $v) {
            $variationSku = $skuBase.'-'.strtoupper($v['color']).'-'.strtoupper($v['material']);

            $normalizedVariations[] = [
                'sku' => $variationSku,
                'price' => $raw['price'] + ($v['additional_price'] ?? 0),
                'active' => ! $status,
                'options' => [
                    'color' => $v['color'],
                    'material' => $v['material'],
                ],
            ];
        }

        $variationsJSON = [
            'attributes' => [
                ['name' => 'color', 'values' => $colorValues],
                ['name' => 'material', 'values' => $materialValues],
            ],
            'variations' => $normalizedVariations,
        ];

        return new NormalizedProductDTO([
            'id' => (int) $raw['id'],
            'name' => $raw['name'],
            'price' => (float) $raw['price'],
            'sku' => $skuBase,
            'status' => $status,
            'variations' => $variationsJSON,
            'warehouses' => [
                'warehouses' => [],
            ],
        ]);
    }
}
