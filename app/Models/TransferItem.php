<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferItem extends Model
{
    protected $fillable = [
        'transfer_id',
        'item_id',
        'item_type',
        'quantity',
        'notes'
    ];

    public function item()
    {
        return $this->morphTo('item', 'item_type', 'item_id');
    }

    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }
}
