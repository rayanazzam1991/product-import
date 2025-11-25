<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StagingProduct extends Model
{
    protected $guarded = [];

    public function items(): HasMany
    {
        return $this->hasMany(StagingProductItem::class);
    }
}
