<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPartRequirement extends Model
{
    protected $fillable = [
        'product_part_id',
        'required_item_id',
        'required_item_type',
        'quantity',
        'unit'
    ];

    protected $casts = [
        'quantity' => 'decimal:4'
    ];

    public function productPart()
    {
        return $this->belongsTo(ProductPart::class);
    }

    public function requiredItem()
    {
        return $this->morphTo('required_item', 'required_item_type', 'required_item_id');
    }
}
