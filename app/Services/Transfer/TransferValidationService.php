<?php

namespace App\Services\Transfer;

use App\Models\Color;
use App\Models\Product;
use App\Models\ProductPart;
use App\Models\RawMaterial;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransferValidationService
{
    public function validateItems(array $items): void
    {
        foreach ($items as $index => $item) {
            $itemType = $item['item_type'] ?? null;
            $itemId = $item['item_id'] ?? null;
            $colorId = $item['color_id'] ?? null;
            $rowCounter = $index + 1;

            if (!class_exists($itemType)) {
                throw ValidationException::withMessages([
                    'validate_outgoing' => ["نوع آیتم نامعتبر در ردیف {$rowCounter}: {$itemType}"]
                ]);
            }

            if (!is_subclass_of($itemType, \Illuminate\Database\Eloquent\Model::class)) {
                throw ValidationException::withMessages([
                    'validate_outgoing' => ["مدل ارث‌برده از Eloquent نیست در ردیف {$rowCounter}: {$itemType}"]
                ]);
            }

            $exists = $itemType::where('id', $itemId)->exists();
            if (!$exists) {
                throw ValidationException::withMessages([
                    'validate_outgoing' => ["آیتم با شناسه {$itemId} در مدل {$itemType} یافت نشد (ردیف {$rowCounter})."]
                ]);
            }

            if ($itemType === Product::class && !$colorId) {
                throw ValidationException::withMessages([
                    'validate_outgoing' => ["برای محصول در ردیف {$rowCounter}، رنگ الزامی است."]
                ]);
            }

            // ToDo: check $colorId for ProductPart in case of receiver is fabric cutter
            if (
                // ($request->from_inventory_type || $request->from_user_id) &&
                $itemType === ProductPart::class &&
                !$colorId
            ) {
                throw ValidationException::withMessages([
                    'validate_outgoing' => ["برای زیر محصول در ردیف {$rowCounter}، رنگ الزامی است."]
                ]);
            }

            if ($colorId && !Color::find($colorId)) {
                throw ValidationException::withMessages([
                    'validate_outgoing' => ["رنگ با شناسه {$colorId} یافت نشد (ردیف {$rowCounter})."]
                ]);
            }
        }
    }

    public function validateInventoryAccess($inventory): void
    {
        if ($inventory && $inventory->is_locked) {
            throw ValidationException::withMessages([
                'inventory_is_locked' => [
                    'انبار در حال انبارگردانی است. تا پایان انبارگردانی نمی‌توان حواله ثبت کرد.'
                ]
            ]);
        }
    }

    public function validateInventoryAvailability(array $items, $fromInventory): void
    {
        $groupedItems = collect($items)->groupBy(function ($item) {
            return $item['item_id'] . '-' . $item['item_type'] . '-' . ($item['color_id'] ?? 'null');
        });

        foreach ($groupedItems as $group) {
            $totalQuantity = $group->sum('quantity');
            $firstItem = $group->first();

            $strategy = \App\Services\TransferItemStrategy\StrategyResolver::resolve($firstItem);

            $validationError = $strategy->validateOutgoing($fromInventory->id, $firstItem);

            if (!empty($validationError)) {
                throw ValidationException::withMessages($validationError);
            }
        }
    }
}
