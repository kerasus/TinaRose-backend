<?php

namespace App\Models;

use App\Enums\TransferStatusType;
use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    protected $fillable = [
        'from_user_id',
        'to_user_id',
        'from_inventory_id',
        'to_inventory_id',
        'transfer_date',
        'status',
        'description',
        'approved_at',
        'rejected_at'
    ];

    protected $dates = ['transfer_date'];

    protected $casts = [
        'to_inventory_id' => 'integer',
        'from_inventory_id' => 'integer',
        'to_user_id' => 'integer',
        'from_user_id' => 'integer',
        'transfer_date' => 'date',
        'status' => TransferStatusType::class,
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime'
    ];

    protected $appends = ['status_label'];

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function fromInventory()
    {
        return $this->belongsTo(Inventory::class, 'from_inventory_id');
    }

    public function toInventory()
    {
        return $this->belongsTo(Inventory::class, 'to_inventory_id');
    }

    public function items()
    {
        return $this->hasMany(TransferItem::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status?->label() ?? 'نا مشخص';
    }
}
