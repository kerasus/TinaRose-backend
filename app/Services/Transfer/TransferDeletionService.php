<?php

namespace App\Services\Transfer;

use App\Models\Transfer;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Enums\TransferStatusType;
use Illuminate\Validation\ValidationException;
use App\Services\TransferItemStrategy\StrategyResolver;

class TransferDeletionService
{
    /**
     * @throws ValidationException
     */
    public function delete(Transfer $transfer): void
    {
        if ($transfer->status === TransferStatusType::Approved) {
            $this->validateReverseApprovedTransfer($transfer);
        }

        $this->updateInventoryOnDelete($transfer);

        $transfer->items()->delete();
        $transfer->delete();
//        DB::transaction(function () use ($transfer) {
//        });
    }

    /**
     * @throws ValidationException
     */
    private function validateReverseApprovedTransfer(Transfer $transfer): void
    {
        $transferItems = $transfer->items;
        $fromInventory = null;
        $toInventory = null;

        if ($transfer->from_inventory_id) {
            $fromInventory = Inventory::find($transfer->from_inventory_id);

            if ($fromInventory && $fromInventory->is_locked) {
                throw ValidationException::withMessages([
                    'from_inventory_is_locked' => [
                        'انبار مبدآ در حال انبارگردانی است. تا پایان انبارگردانی نمی‌توان حواله مربوط به آن را حذف کرد.'
                    ]
                ]);
            }
        }

        if ($transfer->to_inventory_id) {
            $toInventory = Inventory::find($transfer->to_inventory_id);

            if ($toInventory && $toInventory->is_locked) {
                throw ValidationException::withMessages([
                    'to_inventory_is_locked' => [
                        'انبار مقصد در حال انبارگردانی است. تا پایان انبارگردانی نمی‌توان حواله مربوط به آن را حذف کرد.'
                    ]
                ]);
            }
        }

        foreach ($transferItems as $transferItem) {
            if ($fromInventory) {
                $strategy = StrategyResolver::resolve($transferItem);
                $validationError = $strategy->validateReverseOutgoing($transferItem);
                if (!empty($validationError)) {
                    throw ValidationException::withMessages($validationError);
                }
            }

            if ($toInventory) {
                $strategy = StrategyResolver::resolve($transferItem);
                $validationError = $strategy->validateReverseIncoming($transferItem);
                if (!empty($validationError)) {
                    throw ValidationException::withMessages($validationError);
                }
            }
        }
    }

    private function updateInventoryOnDelete(Transfer $transfer): void
    {
        if ($transfer->status !== TransferStatusType::Approved) {
            return;
        }

        foreach ($transfer->items as $item) {
            $strategy = StrategyResolver::resolve($item);

            if ($transfer->from_inventory_id) {
                $fromItem = InventoryItem::firstOrCreate(
                    [
                        'inventory_id' => $transfer->from_inventory_id,
                        'item_id' => $item->item_id,
                        'item_type' => $item->item_type,
                        'color_id' => $item->color_id ?? null
                    ]
                );

                $strategy->reverseOutgoing($fromItem, $item->quantity);
            }

            if ($transfer->to_inventory_id) {
                $toItem = InventoryItem::firstOrCreate(
                    [
                        'inventory_id' => $transfer->to_inventory_id,
                        'item_id' => $item->item_id,
                        'item_type' => $item->item_type,
                        'color_id' => $item->color_id ?? null
                    ]
                );

                $strategy->reverseIncoming($toItem, $item->quantity);
            }
        }
    }
}
