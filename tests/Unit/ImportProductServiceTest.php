<?php

use App\Models\Product;
use App\Models\ProductOptionVariation;
use App\Models\ProductVariation;
use App\Models\Warehouse;
use App\Services\ImportProductService;

describe('ImportProductService::getRecords', function () {
    it('parses CSV rows and skips malformed records', function () {
        $csvPath = tempnam(sys_get_temp_dir(), 'products');

        file_put_contents($csvPath, implode(PHP_EOL, [
            'id,name,sku,status,price,currency,variations,warehouses',
            '1,Shirt,SKU-1,active,19.99,USD,"{""attributes"":[],""variations"":[]}","[]"',
            '2,Invalid Row',
            '3,Pants,SKU-3,deleted,29.50,USD,,',
            '',
        ]));

        $service = new ImportProductService();

        $records = iterator_to_array($service->getRecords($csvPath));

        expect($records)->toHaveCount(2)
            ->and($records[0]['id'])->toBe('1')
            ->and($records[0]['price'])->toBe(19.99)
            ->and($records[1]['status'])->toBe('deleted');

        unlink($csvPath);
    });
});

describe('ImportProductService database syncing', function () {
    it('upserts products while ignoring warehouse columns', function () {
        $service = new ImportProductService();

        $service->insertProducts([
            [
                'id' => 100,
                'name' => 'First Name',
                'sku' => 'SKU-100',
                'price' => 10.5,
                'currency' => 'USD',
                'variations' => json_encode([]),
                'status' => 'active',
                'warehouses' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $product = Product::find(100);

        expect($product)
            ->name->toBe('First Name')
            ->and($product->getAttributes())->not->toHaveKey('warehouses');

        $service->insertProducts([
            [
                'id' => 100,
                'name' => 'Updated Name',
                'sku' => 'SKU-100',
                'price' => 12.75,
                'currency' => 'USD',
                'variations' => json_encode(['test' => true]),
                'status' => 'inactive',
                'warehouses' => json_encode(['ignored' => true]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $product->refresh();

        expect($product)
            ->name->toBe('Updated Name')
            ->and((float) $product->price)->toBe(12.75)
            ->and($product->status)->toBe('inactive')
            ->and($product->variations)->toEqual(['test' => true]);
    });

    it('syncs relations for imported product data', function () {
        $variations = [
            'attributes' => [
                ['name' => 'color', 'values' => ['red', 'blue']],
                ['name' => 'material', 'values' => ['cotton']],
            ],
            'variations' => [
                [
                    'sku' => 'SKU-RED-COTTON',
                    'price' => 99,
                    'active' => true,
                    'options' => [
                        'color' => 'red',
                        'material' => 'cotton',
                    ],
                ],
                [
                    'sku' => 'SKU-BLUE-COTTON',
                    'price' => 105,
                    'active' => false,
                    'options' => [
                        'color' => 'blue',
                        'material' => 'cotton',
                    ],
                ],
            ],
        ];

        $warehouseData = [
            'warehouses' => [
                [
                    'name' => 'Main Hub',
                    'location' => 'Cairo',
                    'inventories' => [
                        ['variation_sku' => 'SKU-RED-COTTON', 'quantity' => 5],
                        ['variation_sku' => 'SKU-BLUE-COTTON', 'quantity' => 3],
                    ],
                ],
            ],
        ];

        $product = Product::create([
            'id' => 200,
            'name' => 'Imported Product',
            'sku' => 'SKU-200',
            'price' => 50,
            'currency' => 'USD',
            'status' => 'active',
            'variations' => json_encode($variations),
        ]);

        $records = [[
            'id' => $product->id,
            'warehouses' => json_encode($warehouseData),
        ]];

        $service = new ImportProductService();
        $service->syncAllProductsRelations($records);

        $productVariations = ProductVariation::where('product_id', $product->id)->get();
        $inventory = Warehouse::first()->inventories()->get();

        expect($productVariations)->toHaveCount(2)
            ->and($productVariations->first()->options()->count())->toBe(2)
            ->and(ProductOptionVariation::count())->toBe(4)
            ->and($inventory)->toHaveCount(2)
            ->and($inventory->pluck('quantity')->sort()->values()->all())->toBe([3, 5]);
    });
});
