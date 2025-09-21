<?php

namespace App\Services\TransferItemStrategy;

use App\Models\InventoryItem;

class DefaultTransferStrategy implements TransferStrategyInterface
{
    public function handleOutgoing(InventoryItem $inventoryItem, float $quantity): void
    {
        $inventoryItem->decrement('quantity', $quantity);
    }

    public function handleIncoming(InventoryItem $inventoryItem, float $quantity): void
    {
        $inventoryItem->increment('quantity', $quantity);
    }

    public function reverseOutgoing(InventoryItem $inventoryItem, float $quantity): void
    {
        $inventoryItem->increment('quantity', $quantity);
    }

    public function reverseIncoming(InventoryItem $inventoryItem, float $quantity): void
    {
        $inventoryItem->decrement('quantity', $quantity);
    }
}
