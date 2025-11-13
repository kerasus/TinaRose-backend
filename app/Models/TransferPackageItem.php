<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferPackageItem extends Model
{
    protected $fillable = [
        'transfer_package_id',
        'item_id',
        'item_type',
        'color_id',
        'quantity',
        'notes'
    ];

    protected $casts = [
        'id' => 'integer',
        'item_id' => 'integer',
        'color_id' => 'integer',
        'transfer_package_id' => 'integer',
        'quantity' => 'decimal:2'
    ];

    public function item()
    {
        return $this->morphTo('item', 'item_type', 'item_id');
    }

    public function color()
    {
        return $this->belongsTo(Color::class);
    }

    public function package()
    {
        return $this->belongsTo(TransferPackage::class, 'transfer_package_id');
    }
}
