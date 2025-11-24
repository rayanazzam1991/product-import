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
    public function sync(Product $product, array $variationsData, array $warehouseData)
    {
        DB::transaction(function () use ($product, $variationsData, $warehouseData) {

            // Reset all relations for this product
            ProductVariation::where('product_id', $product->id)->delete();
            ProductOption::where('product_id', $product->id)->delete();

            /** 1. ATTRIBUTES */
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

            /** 2. PRODUCT OPTIONS */
            $optionIdMap = [];

            foreach ($variationsData['attributes'] as $attr) {
                foreach ($attr['values'] as $value) {
                    $opt = ProductOption::create([
                        'product_id' => $product->id,
                        'product_attribute_id' => $attributeIdMap[$attr['name']],
                        'value' => $value,
                    ]);

                    $optionIdMap["{$attr['name']}:{$value}"] = $opt->id;
                }
            }

            /** 3. PRODUCT VARIATIONS */
            $variationIdMap = [];

            foreach ($variationsData['variations'] as $v) {
                $variation = ProductVariation::create([
                    'product_id' => $product->id,
                    'sku' => $v['sku'],
                    'price' => $v['price'],
                    'active' => $v['active'],
                ]);

                $variationIdMap[$v['sku']] = $variation->id;

                // Option linking
                foreach ($v['options'] as $attr => $val) {
                    $key = "$attr:$val";
                    if (isset($optionIdMap[$key])) {
                        ProductOptionVariation::create([
                            'product_option_id' => $optionIdMap[$key],
                            'product_variation_id' => $variation->id,
                        ]);
                    }
                }
            }

            /** 4. WAREHOUSE INVENTORIES */
            if ($warehouseData && isset($warehouseData['warehouses'])) {

                foreach ($warehouseData['warehouses'] as $w) {

                    $warehouse = Warehouse::firstOrCreate([
                        'name' => $w['name'],
                        'location' => $w['location'] ?? null,
                    ]);

                    foreach ($w['inventories'] as $inv) {
                        $sku = $inv['variation_sku'];

                        if (! isset($variationIdMap[$sku])) {
                            continue;
                        }

                        WarehouseInventory::updateOrCreate(
                            [
                                'warehouse_id' => $warehouse->id,
                                'product_variation_id' => $variationIdMap[$sku],
                            ],
                            [
                                'quantity' => $inv['quantity'],
                            ]
                        );
                    }
                }
            }

        });
    }
}
