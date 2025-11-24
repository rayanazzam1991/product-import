<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductOption;
use App\Models\ProductOptionVariation;
use App\Models\ProductVariation;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ImportProducts extends Command
{
    protected $signature = 'app:import-products {--chunk=500}';

    protected $description = 'Import products, variations, attributes, options, and warehouse inventories.';

    public function handle(): int
    {
        $filePath = storage_path('products_with_warehouses.csv');

        if (! File::exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return CommandAlias::FAILURE;
        }

        $this->info('Starting import...');
        $start = Carbon::now();
        $chunkSize = (int) $this->option('chunk');

        $chunk = [];
        $total = 0;

        try {
            foreach ($this->getRecords($filePath) as $record) {
                $total++;
                // Set timestamps for upsert
                $record['created_at'] = $start;
                $record['updated_at'] = $start;

                $chunk[] = $record;

                if (count($chunk) >= $chunkSize) {
                    $this->insertProducts($chunk);
                    // Process relations for the current chunk immediately after insert
                    $this->syncAllProductsRelations($chunk);
                    $chunk = [];
                }
            }

            if (! empty($chunk)) {
                $this->insertProducts($chunk);
                $this->syncAllProductsRelations($chunk);
            }

            // Cleanup: remove products not updated in this import
            // Use created_at timestamp to avoid deleting newly created products in the current run
            DB::table('products')
                ->where('updated_at', '<', $start)
                ->where('created_at', '<', $start)
                ->delete();

            $this->info("Import completed successfully! Total records processed: $total");

            return CommandAlias::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Error: '.$e->getMessage());

            return CommandAlias::FAILURE;
        }
    }

    /**
     * Insert or update products into the database.
     */
    private function insertProducts(array $chunk)
    {
        // Remove the 'warehouses' key from the chunk as it is not a column in the 'products' table.
        $upsertData = array_map(function ($record) {
            unset($record['warehouses']);

            return $record;
        }, $chunk);

        DB::table('products')->upsert(
            $upsertData,
            ['id'],
            ['name', 'sku', 'price', 'currency', 'variations', 'status', 'updated_at']
        );
    }

    /**
     * Sync relations for a batch of product records.
     */
    private function syncAllProductsRelations(array $productRecords)
    {
        // Iterate through the records from the CSV (which contain the 'warehouses' data)
        foreach ($productRecords as $record) {
            // Re-fetch the Product model instance to ensure we have the correct ID and Eloquent object
            $product = Product::find($record['id']);

            if ($product) {
                // Pass the model and the full record data
                $this->syncProductRelations($product, $record['warehouses']);
            }
        }
    }

    /**
     * Sync variations, options, and inventory for a single product.
     */
    private function syncProductRelations(Product $product, ?string $warehouseJson)
    {
        // 'variations' is stored in the DB, so we access it via the model
        $variationsData = json_decode($product->variations, true);
        $warehouseData = json_decode($warehouseJson, true); // Use the passed JSON string

        if (! $variationsData) {
            return;
        }

        DB::transaction(function () use ($product, $variationsData, $warehouseData) {

            // Remove old entries related to this product before syncing new ones
            ProductVariation::where('product_id', $product->id)->delete();
            ProductOption::where('product_id', $product->id)->delete();

            /** ------------------------------------
             * 1. ATTRIBUTES + VALUES
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
             * ------------------------------------ */
            $optionIdMap = [];

            foreach ($variationsData['attributes'] as $attr) {
                foreach ($attr['values'] as $value) {
                    // Use updateOrCreate in case this option already exists from a previous run
                    $opt = ProductOption::updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'product_attribute_id' => $attributeIdMap[$attr['name']],
                            'value' => $value,
                        ],
                        [
                            'product_attribute_id' => $attributeIdMap[$attr['name']], // Redundant, but ensures structure
                        ]
                    );

                    $optionIdMap["{$attr['name']}:{$value}"] = $opt->id;
                }
            }

            /** ------------------------------------
             * 3. PRODUCT VARIATIONS
             * ------------------------------------ */
            $variationIdMap = []; // SKU → ID

            foreach ($variationsData['variations'] as $v) {
                $variation = ProductVariation::create([
                    'product_id' => $product->id,
                    'sku' => $v['sku'],
                    'price' => $v['price'],
                    'active' => $v['active'] ?? true, // Default to true if not specified
                ]);

                $variationIdMap[$v['sku']] = $variation->id;

                // link options
                foreach ($v['options'] as $attr => $value) {
                    // Check if the option key exists before trying to link
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
             * ------------------------------------ */
            if ($warehouseData && isset($warehouseData['warehouses'])) {

                // Note: Inventory should only be synced for variations created/updated in this run.
                // We rely on $variationIdMap to check existence.

                foreach ($warehouseData['warehouses'] as $w) {

                    $warehouse = Warehouse::firstOrCreate([
                        'name' => $w['name'],
                        // location is nullable in the migration, so only include if set
                        'location' => $w['location'] ?? null,
                    ]);

                    if (isset($w['inventories'])) {
                        foreach ($w['inventories'] as $inv) {

                            $variationSku = $inv['variation_sku'];

                            if (! isset($variationIdMap[$variationSku])) {
                                continue; // Skip if variation was not processed or doesn't belong to this product
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

    /**
     * Generator to read CSV records.
     */
    private function getRecords(string $filePath): \Generator
    {
        $fh = fopen($filePath, 'r');
        $headers = fgetcsv($fh, 5000, ',');

        // Handle headers and map their positions
        $map = array_flip($headers);

        while ($row = fgetcsv($fh, 5000, ',')) {
            // Check if row has enough columns to match headers
            if (count($row) != count($headers)) {
                $this->warn('Skipping malformed row: '.implode(',', $row));

                continue;
            }

            yield [
                'id' => $row[$map['id']],
                'name' => $row[$map['name']],
                'sku' => $row[$map['sku']],
                'status' => $row[$map['status']],
                'price' => (float) ($row[$map['price']] ?? 0.0), // Safely cast price
                'currency' => $row[$map['currency']],
                'variations' => $row[$map['variations']],
                'warehouses' => $row[$map['warehouses']] ?? null, // Will contain the JSON string
            ];
        }

        fclose($fh);
    }
}
