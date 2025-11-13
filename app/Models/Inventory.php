<?php

namespace App\Models;

use App\Enums\TransferStatusType;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $fillable = ['user_id', 'type', 'name', 'description'];

    protected $appends = [
        'has_open_inventory_count',
        'has_pending_transfers',
        'is_locked'
    ];

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function inventoryCounts()
    {
        return $this->hasMany(InventoryCount::class, 'inventory_id');
    }

    public function transfersAsFrom()
    {
        return $this->hasMany(Transfer::class, 'from_inventory_id');
    }

    public function transfersAsTo()
    {
        return $this->hasMany(Transfer::class, 'to_inventory_id');
    }

    // ==============================
    // Attributes
    // ==============================

    /**
     * Check if this inventory has an open (unlocked) inventory count.
     *
     * @return bool
     */
    public function getHasOpenInventoryCountAttribute(): bool
    {
        return $this->inventoryCounts()
            ->where('is_locked', false)
            ->exists();
    }

    /**
     * Check if this inventory has any pending transfers (incoming or outgoing).
     *
     * @return bool
     */
    public function getHasPendingTransfersAttribute(): bool
    {
        return Transfer::where(function ($query) {
            $query->where('from_inventory_id', $this->id)
                ->orWhere('to_inventory_id', $this->id);
        })
            ->where('status', TransferStatusType::Pending)
            ->exists();
    }

    /**
     * Check if this inventory is currently locked.
     *
     * Locked if:
     * - Has an open inventory count
     * - Has pending transfers
     *
     * @return bool
     */
    public function getIsLockedAttribute(): bool
    {
        return $this->has_open_inventory_count || $this->has_pending_transfers;
    }
}
