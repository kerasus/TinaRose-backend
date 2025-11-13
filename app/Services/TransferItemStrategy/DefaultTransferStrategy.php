<?php

namespace App\Services\TransferItemStrategy;

use App\Models\Inventory;
use App\Models\Transfer;
use App\Models\TransferItem;
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

    public function validateOutgoing(int $fromInventoryId, array $inventoryItemData): array|bool
    {
        $inventoryItem = InventoryItem::where([
            ['inventory_id', $fromInventoryId],
            ['item_id', $inventoryItemData['item_id']],
            ['item_type', $inventoryItemData['item_type']],
            ['color_id', $inventoryItemData['color_id'] ?? null]
        ])->first();

        if (!$inventoryItem) {
            return [
                'errors' => [
                    'validate_outgoing' => 'آیتم مورد نظر در انبار مبدأ وجود ندارد.'
                ]
            ];
        }

        $itemQuantity = $inventoryItemData['quantity'];
        $available = $inventoryItem->quantity ?? 0;

        if ($available < $itemQuantity) {
            $name = $inventoryItem->item?->name ?? 'آیتم نامشخص';
            return [
                'errors' => [
                    'validate_outgoing' => "موجودی کافی نیست برای «{$name}». <br> درخواست: {$itemQuantity}, موجود: {$available}"
                ]
            ];
        }

        return []; // بدون خطا
    }

    public function validateReverseOutgoing(TransferItem $transferItem): array|bool
    {
        return [];
    }

    public function validateReverseIncoming(TransferItem $transferItem): array|bool
    {
        $transfer = $transferItem->transfer;

        $toInventory = $transfer->toInventory;

        if ($toInventory) {
            $inventoryItem = InventoryItem::where([
                ['inventory_id', $toInventory->id],
                ['item_id', $transferItem->item_id],
                ['item_type', $transferItem->item_type],
                ['color_id', $transferItem->color_id ?? null]
            ])->first();

            if (!$inventoryItem) {
                return [
                    'errors' => [
                        'validate_outgoing' => 'آیتم مورد نظر در انبار مبدأ وجود ندارد.'
                    ]
                ];
            }

            $itemQuantity = $transferItem->quantity;
            $available = $inventoryItem->quantity ?? 0;

            if ($available < $itemQuantity) {
                $name = $inventoryItem->item?->name ?? 'آیتم نامشخص';
                return [
                    'errors' => [
                        'validate_reverse_outgoing' => "موجودی کافی نیست برای برگشت «{$name}». <br> درخواست: {$itemQuantity}, موجود: {$available}"
                    ]
                ];
            }

            return [];
        }

        return [];
    }
}
