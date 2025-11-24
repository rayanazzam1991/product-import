<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'variations' => 'array', // <-- Add this
    ];

    /**
     * Get the variations for the product.
     */
    public function productVariations(): HasMany
    {
        return $this->hasMany(ProductVariation::class);
    }

    /**
     * Get the options for the product (e.g., all available sizes and colors).
     */
    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class);
    }
}
