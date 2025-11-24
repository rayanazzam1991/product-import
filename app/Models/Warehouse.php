<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $guarded = [];

    /**
     * Get all inventory records stored in this warehouse.
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(WarehouseInventory::class);
    }
}
