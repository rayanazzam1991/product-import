<?php

namespace App\Console\Commands;

use App\Enum\ProductSourceEnum;
use App\Jobs\FetchProductsJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'products:fetch';

    /**
     * The console command description.
     */
    protected $description = 'Command description.';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        Log::info('FetchProductsCommand');
        FetchProductsJob::dispatchSync(ProductSourceEnum::MOCK_SUPPLIER->value);
    }
}
