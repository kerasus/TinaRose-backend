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
        'color_id',
        'initial_stock',
        'notes'
    ];

    protected $appends = ['current_stock'];

    protected $casts = [
        'id' => 'integer',
        'item_id' => 'integer',
        'color_id' => 'integer',
        'inventory_id' => 'integer',
    ];

    public function item()
    {
        return $this->morphTo('item', 'item_type', 'item_id');
    }

    public function color()
    {
        return $this->belongsTo(Color::class);
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class, 'user_inventory_id');
    }

    public function getCurrentStockAttribute(): float
    {
//        return $this->quantity + $this->item->initial_stock;
        return $this->quantity + $this->initial_stock;
    }

    public function scopeNonZero($query)
    {
        return $query->where('quantity', '<>', 0);
    }
}
