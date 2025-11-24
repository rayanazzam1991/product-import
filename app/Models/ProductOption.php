<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductOption extends Model
{
    protected $guarded = [];

    /**
     * Get the product this option belongs to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the base attribute (e.g., 'Color').
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class, 'product_attribute_id');
    }

    /**
     * Get the variations that use this option.
     */
    public function variations(): BelongsToMany
    {
        // Based on the 'product_option_variations' pivot table
        return $this->belongsToMany(ProductVariation::class, 'product_option_variations');
    }
}
