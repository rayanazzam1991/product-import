<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Get the parent product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the options that define this variation (e.g., Size=L, Color=Red).
     */
    public function options(): BelongsToMany
    {
        // Based on the 'product_option_variations' pivot table
        return $this->belongsToMany(ProductOption::class, 'product_option_variations');
    }

    /**
     * Get the inventory records across all warehouses for this variation.
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(WarehouseInventory::class);
    }
}
