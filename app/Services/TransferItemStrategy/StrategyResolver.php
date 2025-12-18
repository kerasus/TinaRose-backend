<?php

namespace App\Services\TransferItemStrategy;

use App\Models\Product;
use App\Models\ProductPart;
use App\Models\TransferItem;

class StrategyResolver
{
    /**
     * Resolve strategy based on transfer item or raw data array
     *
     * @param TransferItem|array $item
     * @return TransferStrategyInterface
     */
    public static function resolve($item): TransferStrategyInterface
    {
        if (is_array($item)) {
            $itemType = $item['item_type'] ?? null;
        } else {
            $itemType = $item->item_type ?? null;
        }

        if (!$itemType || !class_exists($itemType)) {
            throw new \InvalidArgumentException('Invalid item type provided.');
        }

        return match($itemType) {
            Product::class => new ProductTransferStrategy(),
//            ProductPart::class => new ProductPartTransferStrategy(),
//            ProductPart::class, // merge with default
//            RawMaterial::class => new DefaultTransferStrategy(), // merge with default
            default => new DefaultTransferStrategy()
        };
    }
}
