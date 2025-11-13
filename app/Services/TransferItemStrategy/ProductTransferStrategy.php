<?php

namespace App\Services\TransferItemStrategy;

use App\Models\Color;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\ProductPart;
use App\Models\TransferItem;
use App\Models\InventoryItem;

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

                    // ToDo: ...
                    $colorId = null;
                    $requiredItemType = $requirement->required_item_type->value ?? $requirement->required_item_type;
                    if (in_array($requiredItemType, [ProductPart::class, Product::class])) {
                        $colorId = $inventoryItem->color_id ?? null;
                    }

                    $requiredQuantity = $requirement->quantity * $remainingQuantity;
                    $subItem = InventoryItem::firstOrCreate(
                        [
                            'inventory_id' => $inventoryItem->inventory_id,
                            'item_id' => $requirement->required_item_id,
                            'item_type' => $requirement->required_item_type,
                            'color_id' => $colorId
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

    public function validateOutgoing(int $fromInventoryId, array $inventoryItemData): array|bool
    {

        $itemType = $inventoryItemData['item_type'] ?? null;
        $itemId = $inventoryItemData['item_id'] ?? null;
        $itemQuantity = $inventoryItemData['quantity'];
        $availableQuantity = 0;
        $product = $itemType::find($itemId);
        $inventory = Inventory::find($fromInventoryId);
        $isAssemblerInventory = $inventory && $inventory->type === 'assembler';

        $inventoryItem = InventoryItem::where([
            ['inventory_id', $fromInventoryId],
            ['item_id', $inventoryItemData['item_id']],
            ['item_type', $inventoryItemData['item_type']],
            ['color_id', $inventoryItemData['color_id'] ?? null]
        ])->first();

        if ($inventoryItem) {
            $availableQuantity = $inventoryItem->quantity ?? 0;
        }

        if (!$product) {
            return [
                'errors' => [
                    'validate_outgoing' => 'محصول مورد نظر در انبار مبدأ وجود ندارد.'
                ]
            ];
        }

        if (!$isAssemblerInventory) {
            if (!$inventoryItem) {
                return [
                    'errors' => [
                        'validate_outgoing' => 'آیتم مورد نظر در انبار مبدأ وجود ندارد.'
                    ]
                ];
            }

            if ($availableQuantity < $itemQuantity) {
                return [
                    'errors' => [
                        'validate_outgoing' => "موجودی کافی نیست برای «{$product->name}». <br> درخواست: {$itemQuantity}, موجود: {$availableQuantity}"
                    ]
                ];
            }
            return [];
        }

        if ($availableQuantity >= $itemQuantity) {
            return [];
        }

        $remainingQuantity = $itemQuantity - $availableQuantity;

        foreach ($product->requirements as $requirement) {
            $requiredQuantity = $requirement->quantity * $remainingQuantity;

            $colorId = null;
            $requiredItemType = $requirement->required_item_type->value ?? $requirement->required_item_type;
            if (in_array($requiredItemType, [ProductPart::class, Product::class])) {
                $colorId = $inventoryItemData['color_id'] ?? null;
            }

            $subItem = InventoryItem::where([
                ['inventory_id', $fromInventoryId],
                ['item_id', $requirement->required_item_id],
                ['item_type', $requirement->required_item_type],
                ['color_id', $colorId]
            ])->first();


            $subAvailable = $subItem?->quantity ?? 0;

            if ($subAvailable < $requiredQuantity) {
                $itemName = $requirement->requiredItem?->name ?? 'ماده نامشخص';
                if ($colorId) {
                    $color = Color::find($colorId);
                    if ($color) {
                        $itemName .= " ({$color->name})";
                    }
                }

                return [
                    'errors' => [
                        'validate_outgoing' => "موجودی کافی نیست از «{$itemName}»  <br> برای تولید «{$product->name}». <br> مورد نیاز: {$requiredQuantity}, موجود: {$subAvailable}"
                    ]
                ];
            }
        }

        return [];
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
