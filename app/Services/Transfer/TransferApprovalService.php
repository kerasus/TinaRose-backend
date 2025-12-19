<?php

namespace App\Services\Transfer;

use App\Models\User;
use App\Models\Transfer;
use App\Models\InventoryItem;
use App\Enums\TransferStatusType;
use Illuminate\Validation\ValidationException;
use App\Services\TransferItemStrategy\StrategyResolver;

class TransferApprovalService
{
    /**
     * @throws ValidationException
     */
    public function approve(Transfer $transfer, User $approver): Transfer
    {
        if ($transfer->status !== TransferStatusType::Pending) {
            throw ValidationException::withMessages([
                'transfer_status' => ['این حواله قبلاً تأیید یا رد شده است.']
            ]);
        }

        $transfer->update([
            'status' => TransferStatusType::Approved,
            'approver_id' => $approver->id,
            'approved_at' => now()
        ]);

        $this->updateInventoryOnApproved($transfer);

        return $transfer;
    }

    private function updateInventoryOnApproved(Transfer $transfer): void
    {
        foreach ($transfer->items as $item) {
            $strategy = StrategyResolver::resolve($item);

            if ($transfer->from_inventory_id) {
                $fromItem = InventoryItem::firstOrCreate(
                    [
                        'inventory_id' => $transfer->from_inventory_id,
                        'item_id' => $item->item_id,
                        'item_type' => $item->item_type,
                        'color_id' => $item->color_id ?? null
                    ], [
                        'quantity' => 0
                    ]
                );

                if (!$fromItem->exists) {
                    $fromItem->save();
                }

                $strategy->handleOutgoing($fromItem, $item->quantity);
            }

            if ($transfer->to_inventory_id) {
                $toItem = InventoryItem::firstOrCreate(
                    [
                        'inventory_id' => $transfer->to_inventory_id,
                        'item_id' => $item->item_id,
                        'item_type' => $item->item_type,
                        'color_id' => $item->color_id ?? null
                    ], [
                        'quantity' => 0
                    ]
                );

                if (!$toItem->exists) {
                    $toItem->save();
                }

                $strategy->handleIncoming($toItem, $item->quantity);
            }
        }
    }
}
