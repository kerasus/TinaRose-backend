<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryCount extends Model
{
    protected $fillable = ['inventory_id', 'count_date', 'counter_user_id', 'is_locked', 'notes'];

    protected $dates = ['count_date'];

    protected $casts = [
        'id' => 'integer',
        'inventory_id' => 'integer',
        'counter_user_id' => 'integer',
        'is_locked' => 'boolean',
    ];

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function counter()
    {
        return $this->belongsTo(User::class, 'counter_user_id');
    }

    public function items()
    {
        return $this->hasMany(InventoryCountItem::class);
    }
}
