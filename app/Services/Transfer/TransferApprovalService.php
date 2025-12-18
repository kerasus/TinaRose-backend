<?php

namespace App\Services\Transfer;

use App\Models\Transfer;
use App\Models\User;
use App\Enums\TransferStatusType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferApprovalService
{
    public function approve(Transfer $transfer, User $approver): Transfer
    {
        if ($transfer->status !== TransferStatusType::Pending) {
            throw ValidationException::withMessages([
                'transfer_status' => ['این حواله قبلاً تأیید یا رد شده است.']
            ]);
        }

        if ($transfer->to_user_id !== $approver->id) {
            throw ValidationException::withMessages([
                'access_denied' => ['فقط گیرنده این حواله می‌تواند تأیید کند.']
            ]);
        }

        $transfer->update([
            'status' => TransferStatusType::Approved,
            'approved_at' => now()
        ]);

        $this->updateInventoryOnApproved($transfer);

        return $transfer;
    }

    private function updateInventoryOnApproved(Transfer $transfer): void
    {
        foreach ($transfer->items as $item) {
            $strategy = \App\Services\TransferItemStrategy\StrategyResolver::resolve($item);

            if ($transfer->from_inventory_id) {
                $fromItem = \App\Models\InventoryItem::firstOrCreate(
                    [
                        'inventory_id' => $transfer->from_inventory_id,
                        'item_id' => $item->item_id,
                        'item_type' => $item->item_type,
                        'color_id' => $item->color_id ?? null
                    ]
                );

                if (!$fromItem->exists) {
                    $fromItem->save();
                }

                $strategy->handleOutgoing($fromItem, $item->quantity);
            }

            if ($transfer->to_inventory_id) {
                $toItem = \App\Models\InventoryItem::firstOrCreate(
                    [
                        'inventory_id' => $transfer->to_inventory_id,
                        'item_id' => $item->item_id,
                        'item_type' => $item->item_type,
                        'color_id' => $item->color_id ?? null
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
