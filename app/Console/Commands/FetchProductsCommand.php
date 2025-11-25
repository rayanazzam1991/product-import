<?php

namespace App\Console\Commands;

use App\Enum\ProductSourceEnum;
use App\Jobs\FetchProductsJob;
use Illuminate\Console\Command;

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
        FetchProductsJob::dispatch(ProductSourceEnum::MOCK_SUPPLIER->value);
    }
}
