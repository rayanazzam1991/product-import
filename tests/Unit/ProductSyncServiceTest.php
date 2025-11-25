<?php

use App\Jobs\ProcessProductJob;
use App\Jobs\IntegrateWithYallaBuyInventoryManagementJob;
use App\Jobs\NotifyCustomerBackToStockJob;
use App\Jobs\YallaConnectWebhookJob;
use App\Models\StagingProduct;
use App\Services\ProductSyncService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

describe('ProductSyncService::storeDataIntoStagingTable', function () {
    it('stores raw payload and dispatches processing job', function () {
        Queue::fake();

        $payload = json_encode([['id' => 1, 'name' => 'Chair', 'price' => 20, 'variations' => []]]);

        $service = new ProductSyncService();
        $service->storeDataIntoStagingTable($payload);

        $staging = StagingProduct::first();

        expect($staging)
            ->not->toBeNull()
            ->and($staging->raw_payload)->toBe($payload)
            ->and($staging->status)->toBe('pending');

        Queue::assertPushed(ProcessProductJob::class, function ($job) use ($staging) {
            return $job->stagingBatch === $staging->id;
        });
    });
});

describe('ProductSyncService::syncFromExternalApi', function () {
    it('creates staging items and dispatches downstream integration batch', function () {
        Bus::fake();

        $externalProducts = [
            [
                'id' => 10,
                'name' => 'Desk',
                'price' => 120.5,
                'variations' => [
                    ['color' => 'red', 'material' => 'wood', 'additional_price' => 5],
                    ['color' => 'blue', 'material' => 'metal', 'additional_price' => 0],
                ],
                'isDeleted' => false,
            ],
        ];

        $staging = StagingProduct::create([
            'raw_payload' => json_encode($externalProducts),
        ]);

        $service = new ProductSyncService();
        $response = $service->syncFromExternalApi(json_encode($externalProducts), $staging->id);

        $staging->refresh();

        expect($response['success'])->toBeTrue()
            ->and($response['total_products'])->toBe(1)
            ->and($response['staging_id'])->toBe($staging->id)
            ->and($staging->status)->toBe('processing')
            ->and($staging->total_items)->toBe(1)
            ->and($staging->processed_items)->toBe(0)
            ->and($staging->items)->toHaveCount(1)
            ->and($staging->items->first()->status)->toBe('processing');

        Bus::assertBatched(function ($batch) {
            return count($batch->jobs) === 3
                && $batch->name === 'ProductSync_10'
                && $batch->jobs->contains(fn ($job) => $job instanceof NotifyCustomerBackToStockJob)
                && $batch->jobs->contains(fn ($job) => $job instanceof YallaConnectWebhookJob)
                && $batch->jobs->contains(fn ($job) => $job instanceof IntegrateWithYallaBuyInventoryManagementJob);
        });
    });
});
