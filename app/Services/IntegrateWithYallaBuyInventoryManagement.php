<?php

namespace App\Services;

use App\Contracts\IntegrateWithProcurementSystem;

readonly class IntegrateWithYallaBuyInventoryManagement implements IntegrateWithProcurementSystem
{
    public function integrate(): void
    {
        usleep(2 * 1000 * 1000);
    }
}
