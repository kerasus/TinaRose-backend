<?php

namespace App\Observers;

use App\Models\Transfer;
use App\Models\InventoryItem;
use App\Services\TransferItemStrategy\StrategyResolver;

class TransferObserver
{
    /**
     * Handle the Transfer "created" event.
     */
    public function created(Transfer $transfer): void
    {
        foreach ($transfer->items as $item) {
            $strategy = StrategyResolver::resolve($item);

            if ($transfer->from_inventory_id) {
                $fromItem = InventoryItem::where([
                    ['inventory_id', $transfer->from_inventory_id],
                    ['item_id', $item->item_id],
                    ['item_type', $item->item_type]
                ])->first();

                if (!$fromItem) {
                    $fromItem = new InventoryItem([
                        'inventory_id' => $transfer->from_inventory_id,
                        'item_id' => $item->item_id,
                        'item_type' => $item->item_type,
                        'quantity' => 0
                    ]);
                }

                $strategy->handleOutgoing($fromItem, $item->quantity);
            }

            if ($transfer->to_inventory_id) {
                $toItem = InventoryItem::firstOrCreate(
                    [
                        'inventory_id' => $transfer->to_inventory_id,
                        'item_id' => $item->item_id,
                        'item_type' => $item->item_type
                    ]
                );

                $strategy->handleIncoming($toItem, $item->quantity);
            }
        }
    }

    /**
     * Handle the Transfer "updated" event.
     */
    public function updated(Transfer $transfer): void
    {
        //
    }

    /**
     * Handle the Transfer "deleted" event.
     */
    public function deleted(Transfer $transfer): void
    {
        foreach ($transfer->items as $item) {
            $strategy = StrategyResolver::resolve($item);

            if ($transfer->from_inventory_id) {
                $inventoryItem = InventoryItem::where([
                    ['inventory_id', $transfer->from_inventory_id],
                    ['item_id', $item->item_id],
                    ['item_type', $item->item_type]
                ])->first();

                if ($inventoryItem) {
                    $strategy->reverseOutgoing($inventoryItem, $item->quantity);
                }
            }

            if ($transfer->to_inventory_id) {
                $inventoryItem = InventoryItem::where([
                    ['inventory_id', $transfer->to_inventory_id],
                    ['item_id', $item->item_id],
                    ['item_type', $item->item_type]
                ])->first();

                if ($inventoryItem) {
                    $strategy->reverseIncoming($inventoryItem, $item->quantity);
                }
            }
        }
    }

    /**
     * Handle the Transfer "restored" event.
     */
    public function restored(Transfer $transfer): void
    {
        //
    }

    /**
     * Handle the Transfer "force deleted" event.
     */
    public function forceDeleted(Transfer $transfer): void
    {
        //
    }
}
