<?php

namespace App\Services\TransferItemStrategy;

use App\Models\Inventory;
use App\Models\TransferItem;
use App\Models\InventoryItem;

interface TransferStrategyInterface
{
    public function handleOutgoing(InventoryItem $inventoryItem, float $quantity): void;
    public function handleIncoming(InventoryItem $inventoryItem, float $quantity): void;

    public function reverseOutgoing(InventoryItem $inventoryItem, float $quantity): void;
    public function reverseIncoming(InventoryItem $inventoryItem, float $quantity): void;

    public function validateOutgoing(int $fromInventoryId, array $inventoryItemData): array|bool;

    public function validateReverseOutgoing(TransferItem $transferItem): array|bool;
    public function validateReverseIncoming(TransferItem $transferItem): array|bool;
}
