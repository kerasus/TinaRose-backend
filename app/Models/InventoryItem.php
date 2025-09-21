<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = [
        'inventory_id',
        'item_id',
        'item_type',
        'quantity',
        'notes'
    ];

    protected $appends = ['current_stock'];

    public function item()
    {
        return $this->morphTo('item', 'item_type', 'item_id');
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class, 'user_inventory_id');
    }

    public function getCurrentStockAttribute(): float
    {
        return $this->quantity+ $this->item->initial_stock;
    }

    public function scopeNonZero($query)
    {
        return $query->where('quantity', '<>', 0);
    }
}
