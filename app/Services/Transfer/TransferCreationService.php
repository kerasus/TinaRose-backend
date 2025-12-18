<?php

namespace App\Services\Transfer;

use App\Models\Transfer;
use App\Enums\TransferStatusType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferCreationService
{
    private TransferValidationService $validationService;
    private TransferInventoryResolver $inventoryResolver;

    public function __construct(
        TransferValidationService $validationService,
        TransferInventoryResolver $inventoryResolver
    ) {
        $this->validationService = $validationService;
        $this->inventoryResolver = $inventoryResolver;
    }

    public function create(array $data): Transfer
    {
        $validTypes = [
            'fabric_cutter',
            'coloring_worker',
            'molding_worker',
            'assembler',
            'central_warehouse'
        ];

        // --- تعیین انبار مبدأ ---
        $fromInventory = null;
        if ($data['from_inventory_type'] || $data['from_user_id']) {
            $fromInventory = $this->inventoryResolver->resolveFromInventory($data, $validTypes);

            if ($fromInventory) {
                $this->validationService->validateInventoryAccess($fromInventory);

                if (isset($data['items'])) {
                    $this->validationService->validateInventoryAvailability($data['items'], $fromInventory);
                }
            }
        }

        // --- تعیین انبار مقصد ---
        $toInventory = null;
        if ($data['to_inventory_type'] && !$data['to_user_id']) {
            throw ValidationException::withMessages([
                'to_user_id' => ['برای انبار مقصد، کاربر گیرنده الزامی است.']
            ]);
        }

        if ($data['to_inventory_type'] || $data['to_user_id']) {
            $toInventory = $this->inventoryResolver->resolveToInventory($data, $validTypes);

            if ($toInventory) {
                $this->validationService->validateInventoryAccess($toInventory);
            }
        }

        return DB::transaction(function () use ($data, $fromInventory, $toInventory) {
            $transfer = Transfer::create([
                'from_user_id' => $data['from_user_id'] ?? null,
                'to_user_id' => $data['to_user_id'] ?? null,
                'from_inventory_id' => $fromInventory?->id,
                'to_inventory_id' => $toInventory?->id,
                'creator_user_id' => auth()->id(),
                'transfer_date' => $data['transfer_date'],
                'status' => TransferStatusType::Pending,
                'description' => $data['description'] ?? null,
            ]);

            if (isset($data['items'])) {
                foreach ($data['items'] as $item) {
                    $transfer->items()->create($item);
                }
            }

            return $transfer;
        });
    }
}
