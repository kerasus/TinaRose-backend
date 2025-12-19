<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryCount;
use Illuminate\Validation\ValidationException;

class InventoryCountFinalizeService
{
    /**
     * Finalize an inventory count.
     *
     * @param InventoryCount $count
     * @param bool $adjustInventory
     * @return void
     * @throws ValidationException
     */
    public function finalize(InventoryCount $count, bool $adjustInventory = false): void
    {
        $hasMissingActual = $count->items()->whereNull('actual_quantity')->exists();

        if ($hasMissingActual) {
            throw ValidationException::withMessages([
                'hasMissingActual' => ['شمارش هنوز کامل انجام نشده. لطفاً تمام آیتم‌ها را شمارش کنید.']
            ]);
        }

        if ($adjustInventory) {
            foreach ($count->items as $item) {
                if ($item->difference == 0) continue;

                $inventoryItem = InventoryItem::where([
                    ['inventory_id', $count->inventory_id],
                    ['item_id', $item->item_id],
                    ['item_type', $item->item_type],
                    ['color_id', $item->color_id]
                ])->first();

                if ($inventoryItem) {
                    $inventoryItem->update(['quantity' => $item->actual_quantity]);
                } else {
                    InventoryItem::create([
                        'inventory_id' => $count->inventory_id,
                        'item_id' => $item->item_id,
                        'item_type' => $item->item_type,
                        'color_id' => $item->color_id,
                        'quantity' => $item->actual_quantity
                    ]);
                }
            }
        }

        $count->update(['is_locked' => true]);
    }
}
