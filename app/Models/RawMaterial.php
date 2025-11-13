<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RawMaterial extends Model
{
    protected $fillable = [
        'name',
        'code',
        'unit_large',
        'unit_small',
        'conversion_rate'
    ];

    // Polymorphic Inventory
    public function inventory()
    {
        return $this->morphOne(Inventory::class, 'item');
    }

    public function transferItems()
    {
        return $this->morphMany(TransferItem::class, 'item');
    }

    public function requiredInProductRequirements()
    {
        return $this->morphMany(ProductRequirement::class, 'required_item');
    }
}
