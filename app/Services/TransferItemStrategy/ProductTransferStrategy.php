<?php

namespace App\Services\TransferItemStrategy;

use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\ProductRequirement;

class ProductTransferStrategy implements TransferStrategyInterface
{
    public function handleOutgoing(InventoryItem $inventoryItem, float $quantity): void
    {
        $product = $inventoryItem->item;
        $availableQuantity = $inventoryItem->quantity ?? 0;

        $inventory = Inventory::find($inventoryItem->inventory_id);

        $isAssemblerInventory = $inventory && $inventory->type === 'assembler';

        if (!$isAssemblerInventory) {
            $inventoryItem->decrement('quantity', $quantity);
        } else {
            if ($availableQuantity >= $quantity) {
                $inventoryItem->decrement('quantity', $quantity);
            } else {
                $remainingQuantity = $quantity - $availableQuantity;
                if ($availableQuantity > 0) {
                    $inventoryItem->update(['quantity' => 0]);
                }
                foreach ($product->requirements as $requirement) {
                    $requiredQuantity = $requirement->quantity * $remainingQuantity;
                    $subItem = InventoryItem::firstOrCreate(
                        [
                            'inventory_id' => $inventoryItem->inventory_id,
                            'item_id' => $requirement->required_item_id,
                            'item_type' => $requirement->required_item_type
                        ]
                    );

                    $subItem->decrement('quantity', $requiredQuantity);
                }
            }
        }
    }

    public function handleIncoming(InventoryItem $inventoryItem, float $quantity): void
    {
        $inventoryItem->increment('quantity', $quantity);
    }

    public function reverseOutgoing(InventoryItem $inventoryItem, float $quantity): void
    {
        $product = $inventoryItem->item;
        $inventory = Inventory::find($inventoryItem->inventory_id);
        $isAssemblerInventory = $inventory && $inventory->type === 'assembler';

        if (!$isAssemblerInventory) {
            $inventoryItem->increment('quantity', $quantity);
        } else {
            foreach ($product->requirements as $requirement) {
                $requiredQuantity = $requirement->quantity * $quantity;
                $subItem = InventoryItem::firstOrCreate(
                    [
                        'inventory_id' => $inventoryItem->inventory_id,
                        'item_id' => $requirement->required_item_id,
                        'item_type' => $requirement->required_item_type
                    ]
                );

                $subItem->increment('quantity', $requiredQuantity);
            }
        }
    }

    public function reverseIncoming(InventoryItem $inventoryItem, float $quantity): void
    {
        $inventoryItem->decrement('quantity', $quantity);
    }
}
