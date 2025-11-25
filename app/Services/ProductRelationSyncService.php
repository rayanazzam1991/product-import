<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductOption;
use App\Models\ProductOptionVariation;
use App\Models\ProductVariation;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Support\Facades\DB;

class ProductRelationSyncService
{
    /**
     * Sync variations, options, and inventory for a single product.
     * This function is now fully unified and flexible.
     *
     * @param  Product  $product  The product model to sync.
     * @param  array  $variationsData  The variations and attributes data.
     * @param  array  $warehouseData  The warehouse and inventory data.
     */
    public function syncProductRelations(Product $product, array $variationsData, array $warehouseData): void
    {
        // The main logic is wrapped in a transaction
        DB::transaction(function () use ($product, $variationsData, $warehouseData) {

            // --- 0. CLEANUP (Using the approach from both) ---
            // Remove old entries related to this product before syncing new ones
            ProductVariation::where('product_id', $product->id)->delete();
            ProductOption::where('product_id', $product->id)->delete();

            /** ------------------------------------
             * 1. ATTRIBUTES + VALUES
             * (Identical in both original functions)
             * ------------------------------------ */
            $attributeIdMap = [];

            foreach ($variationsData['attributes'] as $attr) {
                $attribute = ProductAttribute::firstOrCreate(['name' => $attr['name']]);
                $attributeIdMap[$attr['name']] = $attribute->id;

                foreach ($attr['values'] as $value) {
                    ProductAttributeValue::firstOrCreate([
                        'product_attribute_id' => $attribute->id,
                        'value' => $value,
                    ]);
                }
            }

            /** ------------------------------------
             * 2. PRODUCT OPTIONS
             * (Using the safer updateOrCreate from the first function)
             * ------------------------------------ */
            $optionIdMap = [];

            foreach ($variationsData['attributes'] as $attr) {
                foreach ($attr['values'] as $value) {
                    // Use updateOrCreate for safety, as in the first function
                    $opt = ProductOption::updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'product_attribute_id' => $attributeIdMap[$attr['name']],
                            'value' => $value,
                        ],
                        [
                            // Optional: Include any other updatable fields here if needed
                        ]
                    );

                    $optionIdMap["{$attr['name']}:{$value}"] = $opt->id;
                }
            }

            /** ------------------------------------
             * 3. PRODUCT VARIATIONS
             * (Using the default 'active' = true from the first function)
             * ------------------------------------ */
            $variationIdMap = []; // SKU → ID

            foreach ($variationsData['variations'] as $v) {
                $variation = ProductVariation::create([
                    'product_id' => $product->id,
                    'sku' => $v['sku'],
                    'price' => $v['price'],
                    'active' => $v['active'] ?? true, // Default to true if not specified (First function logic)
                ]);

                $variationIdMap[$v['sku']] = $variation->id;

                // Link options
                foreach ($v['options'] as $attr => $value) {
                    $optionKey = "$attr:$value";
                    if (isset($optionIdMap[$optionKey])) {
                        ProductOptionVariation::create([
                            'product_option_id' => $optionIdMap[$optionKey],
                            'product_variation_id' => $variation->id,
                        ]);
                    }
                }
            }

            /** ------------------------------------
             * 4. GLOBAL WAREHOUSES + INVENTORIES
             * (Logic is essentially the same, using the passed array)
             * ------------------------------------ */
            if ($warehouseData && isset($warehouseData['warehouses'])) {

                foreach ($warehouseData['warehouses'] as $w) {

                    $warehouse = Warehouse::firstOrCreate([
                        'name' => $w['name'],
                        'location' => $w['location'] ?? null,
                    ]);

                    if (isset($w['inventories'])) {
                        foreach ($w['inventories'] as $inv) {

                            $variationSku = $inv['variation_sku'];

                            if (! isset($variationIdMap[$variationSku])) {
                                continue; // Skip if variation was not processed
                            }

                            WarehouseInventory::updateOrCreate(
                                [
                                    'warehouse_id' => $warehouse->id,
                                    'product_variation_id' => $variationIdMap[$variationSku],
                                ],
                                [
                                    'quantity' => $inv['quantity'],
                                ]
                            );
                        }
                    }
                }
            }

        });
    }
}
