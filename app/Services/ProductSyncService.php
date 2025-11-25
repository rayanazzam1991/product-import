<?php

namespace App\Services;

use App\DTOs\NormalizedProductDTO;
use App\Jobs\IntegrateWithYallaBuyInventoryManagementJob;
use App\Jobs\NotifyCustomerBackToStockJob;
use App\Jobs\ProcessProductJob;
use App\Jobs\YallaConnectWebhookJob;
use App\Models\Product;
use App\Models\StagingProduct;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

readonly class ProductSyncService
{
    public function storeDataIntoStagingTable(string $externalProductsJson): void
    {
        $stagingBatch = StagingProduct::query()->create([
            'raw_payload' => $externalProductsJson,
        ]);

        ProcessProductJob::dispatch($stagingBatch->id);

    }

    /**
     * @throws Throwable
     */
    public function syncFromExternalApi(string $externalProductsJson, int $stagingId): array
    {
        Log::info('syncFromExternalApi');
        $startedAt = now();
        $staging = StagingProduct::findOrFail($stagingId);

        $externalProducts = json_decode($externalProductsJson, true);

        $staging->update([
            'total_items' => count($externalProducts),
            'started_at' => now(),
            'status' => 'processing',
        ]);

        foreach ($externalProducts as $rawProduct) {

            // Save raw product to staging table
            $item = $staging->items()->create([
                'raw_product' => $rawProduct,
                'status' => 'processing',
                'started_at' => now(),
            ]);

            // Build DTO
            $dto = $this->normalizeExternalProduct($rawProduct);

            // Insert/Update product row
            $dbRow = $dto->toDatabaseRow();
            $dbRow['created_at'] = $startedAt;
            $dbRow['updated_at'] = $startedAt;

            // Create job batch for this product
            Bus::batch([
                new NotifyCustomerBackToStockJob($dto),
                new YallaConnectWebhookJob($dto),
                new IntegrateWithYallaBuyInventoryManagementJob($dto),
            ])
                ->then(function () use ($dto, $dbRow, $item, $staging) {

                    DB::transaction(function () use ($dto, $dbRow, $item) {

                        // Persist product ONLY after all jobs succeeded
                        Product::updateOrCreate(
                            ['id' => $dto->id],
                            $dbRow
                        );

                        $product = Product::find($dto->id);

                        /** @var ProductRelationSyncService $relationService */
                        $relationService = app(ProductRelationSyncService::class);
                        $relationService->syncProductRelations(product: $product, variationsData:  $dto->variations, warehouseData:  $dto->variations);

                        $item->update([
                            'status' => 'done',
                            'finished_at' => now(),
                        ]);
                    });

                    // Update staging progress
                    $staging->increment('processed_items');

                    if ($staging->processed_items === $staging->total_items) {
                        $staging->update([
                            'status' => 'done',
                            'finished_at' => now(),
                        ]);
                    }

                })
                ->catch(function ($e) use ($item, $staging) {

                    Log::error('Product batch failed: '.$e->getMessage());

                    $item->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                        'finished_at' => now(),
                    ]);

                    $staging->update(['status' => 'failed']);
                })
                ->name("ProductSync_{$dto->id}")
                ->dispatch();
        }

        $totalDuration = $startedAt->diffInSeconds(now());

        return [
            'success' => true,
            'message' => 'Product sync started asynchronously.',
            'total_products' => count($externalProducts),
            'sync_started_at' => $startedAt,
            'sync_duration_seconds' => $totalDuration,
            'staging_id' => $staging->id,
        ];
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
