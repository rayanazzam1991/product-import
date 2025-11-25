<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ImportProductService;
use App\Services\ProductRelationSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ImportProductsOldStructure extends Command
{
    public function __construct(private readonly ImportProductService $importProductService )
    {
        parent::__construct();
    }
    protected $signature = 'app:import-products-old {--chunk=500}';

    protected $description = 'Import products, variations, attributes, options, and warehouse inventories.';

    public function handle(): int
    {
        $filePath = storage_path('products.csv');

        if (! File::exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return CommandAlias::FAILURE;
        }

        $this->info('Starting import...');
        $start = Carbon::now();
        $chunkSize = (int) $this->option('chunk');

        $chunk = [];
        $total = 0;
        $chunkCount = 0; // Initialize chunk counter

        try {
            foreach ($this->importProductService->getRecords($filePath) as $record) {
                $total++;
                // Set timestamps for upsert
                $record['created_at'] = $start;
                $record['updated_at'] = $start;

                $chunk[] = $record;

                if (count($chunk) >= $chunkSize) {
                    $this->importProductService->insertProducts($chunk);

                    $chunkCount++; // Increment chunk counter
                    $this->line("✅ Chunk #$chunkCount (Items: ".count($chunk).") added. Total processed so far: $total"); // <--- ADDED LINE

                    // Process relations for the current chunk immediately after insert
//                    $this->importProductService->syncAllProductsRelations($chunk);
                    $chunk = [];
                }
            }

            if (! empty($chunk)) {
                $this->importProductService->insertProducts($chunk);

                $chunkCount++; // Increment chunk counter for the final chunk
                $this->line("✅ Final Chunk #$chunkCount (Items: ".count($chunk).") added. Total processed so far: $total"); // <--- ADDED LINE

//                $this->importProductService->syncAllProductsRelations($chunk);
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
            $this->error('Error: '.$e->getTraceAsString());

            Log::error('Error: '.$e->getTraceAsString());

            return CommandAlias::FAILURE;
        }
    }

}
