<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportProductService
{
    /**
     * Insert or update products into the database.
     */
    public function insertProducts(array $chunk): void
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
    public function syncAllProductsRelations(array $productRecords): void
    {
        // Iterate through the records from the CSV (which contain the 'warehouses' data)
        foreach ($productRecords as $record) {
            // Re-fetch the Product model instance to ensure we have the correct ID and Eloquent object
            $product = Product::find($record['id']);

            if ($product) {
                $variationsData = $product->variations; // Assuming this is an array/casts to array
                $warehouseData = $record['warehouses'] ? json_decode($record['warehouses'], true) : [];

                /** @var ProductRelationSyncService $relationService */
                $relationService = app(ProductRelationSyncService::class);
                $relationService->syncProductRelations(product: $product, variationsData: $variationsData, warehouseData: $warehouseData);

            }
        }
    }

    /**
     * Generator to read CSV records.
     */
    public function getRecords(string $filePath): \Generator
    {
        $fh = fopen($filePath, 'r');
        $headers = fgetcsv($fh, 5000, ',');

        // Map header name → column index
        $map = array_flip($headers);

        while ($row = fgetcsv($fh, 5000, ',')) {

            if (count($row) != count($headers)) {
                Log::warning('Skipping malformed row: ' . implode(',', $row));
                continue;
            }

            yield [
                'id'         => array_key_exists('id', $map) ? $row[$map['id']] : null,
                'name'       => array_key_exists('name', $map) ? $row[$map['name']] : null,
                'sku'        => array_key_exists('sku', $map) ? $row[$map['sku']] : null,
                'status'     => array_key_exists('status', $map) ? $row[$map['status']] : null,
                'price'      => array_key_exists('price', $map) ? (float) $row[$map['price']] : 0.0,
                'currency'   => array_key_exists('currency', $map) ? $row[$map['currency']] : null,
                'variations' => array_key_exists('variations', $map) ? $row[$map['variations']] : null,
                'warehouses' => array_key_exists('warehouses', $map) ? $row[$map['warehouses']] : null,
            ];
        }

        fclose($fh);
    }
}
