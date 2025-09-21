<?php

namespace App\Services\TransferItemStrategy;

use App\Models\TransferItem;
use Illuminate\Support\Facades\App;

class StrategyResolver
{
    public static function resolve(TransferItem $item): TransferStrategyInterface
    {
        return match($item->item_type) {
            'App\Models\Product' => App::make(ProductTransferStrategy::class),
            default => App::make(DefaultTransferStrategy::class)
        };
    }
}
