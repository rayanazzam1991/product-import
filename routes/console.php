<?php

use App\Enum\ProductSourceEnum;
use App\Jobs\FetchProductsJob;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    FetchProductsJob::dispatch(ProductSourceEnum::MOCK_SUPPLIER->value);
})
//    ->everyMinute();
    ->dailyAt('00:00')// Run at 12:00 AM
    ->timezone('Asia/Baghdad');


