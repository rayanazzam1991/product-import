<?php

namespace App\Services;

use App\Contracts\IntegrateWithProcurementSystem;
use Illuminate\Support\Facades\Log;

readonly class IntegrateWithYallaBuyInventoryManagement implements IntegrateWithProcurementSystem
{
    public function integrate(): void
    {
        usleep(2 * 1000 * 1000);
    }
}
