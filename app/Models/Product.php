<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'code',
        'unit_large',
        'unit_small',
        'conversion_rate',
        'initial_stock',
        'current_stock'
    ];

//    // Polymorphic Inventory
//    public function inventory()
//    {
//        return $this->morphOne(Inventory::class, 'item');
//    }
//
//    public function transferItems()
//    {
//        return $this->morphMany(TransferItem::class, 'item');
//    }

    // Requirements
    public function requirements()
    {
        return $this->hasMany(ProductRequirement::class);
    }
}
