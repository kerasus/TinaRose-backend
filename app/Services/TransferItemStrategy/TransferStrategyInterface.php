<?php

namespace App\Services\TransferItemStrategy;

use App\Models\InventoryItem;

interface TransferStrategyInterface
{
    public function handleOutgoing(InventoryItem $inventoryItem, float $quantity): void;
    public function handleIncoming(InventoryItem $inventoryItem, float $quantity): void;

    public function reverseOutgoing(InventoryItem $inventoryItem, float $quantity): void;
    public function reverseIncoming(InventoryItem $inventoryItem, float $quantity): void;
}
