<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryCountItem extends Model
{
    protected $fillable = [
        'inventory_count_id', 'item_id', 'item_type', 'color_id',
        'system_quantity', 'actual_quantity', 'difference', 'notes'
    ];

    protected $casts = [
        'id' => 'integer',
        'item_id' => 'integer',
        'color_id' => 'integer',
        'inventory_count_id' => 'integer',
        'system_quantity' => 'decimal:4',
        'actual_quantity' => 'decimal:4',
        'difference' => 'decimal:4'
    ];

    public function item()
    {
        return $this->morphTo('item', 'item_type', 'item_id');
    }

    public function color()
    {
        return $this->belongsTo(Color::class);
    }

    public function count()
    {
        return $this->belongsTo(InventoryCount::class);
    }
}
