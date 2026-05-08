<?php

namespace App\Services\Production;

use App\Models\InventoryCount;
use App\Models\InventoryItem;
use App\Services\Inventory\InventoryCountFinalizeService;
use Throwable;
use App\Models\User;
use App\Models\Color;
use App\Models\Fabric;
use App\Models\Inventory;
use App\Models\Production;
use App\Enums\UserRoleType;
use App\Enums\InventoryType;
use Illuminate\Validation\ValidationException;
use App\Services\Transfer\TransferCreationService;
use App\Services\Transfer\TransferApprovalService;
use App\Services\Transfer\TransferValidationService;

class ProductionApproveService
{
    private TransferCreationService $transferCreationService;
    private TransferValidationService $transferValidationService;
    private TransferApprovalService $transferApprovalService;
    private InventoryCountFinalizeService $inventoryCountFinalizeService;

    public function __construct(
        TransferCreationService $transferCreationService,
        TransferValidationService $transferValidationService,
        TransferApprovalService $transferApprovalService,
        InventoryCountFinalizeService $inventoryCountFinalizeService,
    ) {
        $this->transferCreationService = $transferCreationService;
        $this->transferValidationService = $transferValidationService;
        $this->transferApprovalService = $transferApprovalService;
        $this->inventoryCountFinalizeService = $inventoryCountFinalizeService;
    }

    /**
     * @throws Throwable
     * @throws ValidationException
     */
    public function approve(Production $production, User $approver): void
    {
        // فقط اگر تولید قبلاً تأیید نشده باشه
        if ($production->approved_at) {
            throw ValidationException::withMessages([
                'approve_error' => ['این تولید قبلاً تأیید شده است.']
            ]);
        }

//        // // چک کردن نیازمندی‌ها فقط برای FabricCutter
//         $operator = $production->operator;
//         $operatorRoles = $operator->getRoleNames();
//         $isFabricCutter = $operatorRoles->contains(UserRoleType::FabricCutter->value);
//         $isColoringWorker = $operatorRoles->contains(UserRoleType::ColoringWorker->value);
//         $isMoldingWorker = $operatorRoles->contains(UserRoleType::MoldingWorker->value);
//
//         if ($isFabricCutter) {
//             $this->validateFabricCutterRequirements($production);
//         }
//
//        // // ایجاد حواله خودکار
//         if ($isFabricCutter) {
//             $this->createTransferForFabricCutter($production, $approver);
//         } elseif ($isColoringWorker) {
//             $this->createTransferForColoringWorker($production, $approver);
//         } elseif ($isMoldingWorker) {
//             $this->createTransferForMoldingWorker($production, $approver);
//         }

        // تأیید تولید
        $production->update([
            'approved_by' => $approver->id,
            'approved_at' => now()
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function validateFabricCutterRequirements(Production $production): void
    {
        $fromInventory = $this->getOrCreateInventoryByType(InventoryType::CentralWarehouse);

        $productPart = $production->productPart;


        foreach ($productPart->requirements as $requirement) {
            $requiredQuantity = $production->bunch_count * $productPart->count_per_bunch * $requirement->quantity;

            $itemData = [
                'item_id' => $requirement->required_item_id,
                'item_type' => $requirement->required_item_type,
                'color_id' => null,
                'quantity' => $requiredQuantity
            ];

            $this->transferValidationService->validateInventoryAvailability([$itemData], $fromInventory);
        }
    }

    /**
     * @throws ValidationException
     */
    private function createTransferForFabricCutter(Production $production, User $approver): void
    {
        $fromInventory = $this->getOrCreateInventoryByType(InventoryType::CentralWarehouse);
        $toInventory = $this->getOrCreateInventoryByType(InventoryType::FabricCutter);

        $this->transferValidationService->validateInventoryAccess($fromInventory);
        $this->transferValidationService->validateInventoryAccess($toInventory);

        $productPart = $production->productPart;

        // فقط نیازمندی‌های مرتبط با رنگ یا پارچه تولید
        $relevantRequirement = $productPart->requirements->first(function ($requirement) use ($production) {

            // اگر تولید پارچه دارد
            if ($production->fabric_id) {
                return $requirement->required_item_type === Fabric::class
                    && (int) $requirement->required_item_id === (int) $production->fabric_id;
            }

            // اگر تولید رنگ دارد
            if ($production->color_id) {
                return $requirement->required_item_type === Color::class
                    && (int) $requirement->required_item_id === (int) $production->color_id;
            }

            return false;
//
//            if ($production->fabric_id && $production->color_id) {
//                return $requirement->color_id === $production->color_id &&
//                    $requirement->fabric_id === $production->fabric_id;
//            }
//
//            if ($production->color_id) {
//                return $requirement->color_id === $production->color_id;
//            }
//
//            if ($production->fabric_id) {
//                return $requirement->fabric_id === $production->fabric_id;
//            }
//
//            return !$requirement->color_id && !$requirement->fabric_id;
        });

        $transferItem = null;


        if ($relevantRequirement) {
            $requiredQuantity = $production->bunch_count * $productPart->count_per_bunch * $relevantRequirement->quantity;
            $transferItem = [
                'item_id' => $relevantRequirement->required_item_id,
                'item_type' => $relevantRequirement->required_item_type,
                'color_id' => $relevantRequirement->color_id,
                'fabric_id' => $relevantRequirement->fabric_id,
                'quantity' => $requiredQuantity,
                'notes' => "برای تولید {$productPart->name}"
            ];

            // انتقال نیازمندی های تولید به انبار برشکاری
            $this->createAndApproveTransfer([
                'from_user_id' => null,
                'to_user_id' => $production->user_id,
                'from_inventory_id' => $fromInventory->id,
                'to_inventory_id' => $toInventory->id,
                'transfer_date' => $production->production_date,
                'description' => "حواله خودکار: تولید برشکاری توسط {$production->user->firstname} {$production->user->lastname}",
                'items' => [$transferItem]
            ], $approver);
        }

        // انبارگردانی: تبدیل نیازمندی به زیرمحصول
        $count = InventoryCount::create([
            'inventory_id' => $toInventory->id,
            'count_date' => now(),
            'counter_user_id' => $approver->id,
            'notes' => "انبارگردانی سیستمی: تولید {$productPart->name} از نیازمندی‌ها",
        ]);

        if ($transferItem) {
            // ثبت تغییرات نیازمندی‌ها (کاهش)
            $inventoryItem = InventoryItem::where([
                ['inventory_id', $toInventory->id],
                ['item_id', $transferItem['item_id']],
                ['item_type', $transferItem['item_type']],
                ['color_id', $transferItem['color_id']],
                ['fabric_id', $transferItem['fabric_id']],
            ])->first();

            $systemQuantity = $inventoryItem?->quantity ?? 0;
            $actualQuantity = $systemQuantity - $transferItem['quantity']; // کاهش نیازمندی
            $difference = -$transferItem['quantity'];

            $count->items()->create([
                'item_id' => $transferItem['item_id'],
                'item_type' => $transferItem['item_type'],
                'color_id' => $transferItem['color_id'],
                'fabric_id' => $transferItem['fabric_id'],
                'system_quantity' => $systemQuantity,
                'actual_quantity' => $actualQuantity,
                'difference' => $difference
            ]);
        }

        // ثبت تغییرات زیرمحصول (افزایش)
        $inventoryItem = InventoryItem::where([
            ['inventory_id', $toInventory->id],
            ['item_id', $productPart->id],
            ['item_type', 'App\Models\ProductPart'],
            ['fabric_id', $production->fabric_id],
            ['color_id', $production->color_id]
        ])->first();

        $systemQuantity = $inventoryItem?->quantity ?? 0;
        $producedQuantity = $production->bunch_count * $productPart->count_per_bunch; // تعداد گلبرگ
        $actualQuantity = $systemQuantity + $producedQuantity; // افزایش زیرمحصول
        $difference = $producedQuantity;

        $count->items()->create([
            'item_id' => $productPart->id,
            'item_type' => 'App\Models\ProductPart',
            'fabric_id' => $production->fabric_id,
            'color_id' => $production->color_id,
            'system_quantity' => $systemQuantity,
            'actual_quantity' => $actualQuantity,
            'difference' => $difference
        ]);

        $this->inventoryCountFinalizeService->finalize($count, true);
    }

    /**
     * @throws ValidationException
     */
    private function createTransferForColoringWorker(Production $production, User $approver): void
    {
        $fromInventory = $this->getOrCreateInventoryByType(InventoryType::FabricCutter);
        $toInventory = $this->getOrCreateInventoryByType(InventoryType::ColoringWorker);

        $this->transferValidationService->validateInventoryAccess($fromInventory);
        $this->transferValidationService->validateInventoryAccess($toInventory);

        $transferItem = [
            'item_id' => $production->product_part_id,
            'item_type' => 'App\Models\ProductPart',
            'color_id' => null,
            'quantity' => $production->bunch_count,
            'notes' => "برای رنگ‌آمیزی با رنگ {$production->color->name}"
        ];

        $this->createAndApproveTransfer([
            'from_user_id' => $production->user_id,
            'to_user_id' => null,
            'from_inventory_id' => $fromInventory->id,
            'to_inventory_id' => $toInventory->id,
            'transfer_date' => $production->production_date,
            'description' => "حواله خودکار: تولید رنگکاری توسط {$production->user->firstname} {$production->user->lastname}",
            'items' => [$transferItem]
        ], $approver);
    }

    /**
     * @throws ValidationException
     */
    private function createTransferForMoldingWorker(Production $production, User $approver): void
    {
        $fromInventory = $this->getOrCreateInventoryByType(InventoryType::ColoringWorker);
        $toInventory = $this->getOrCreateInventoryByType(InventoryType::MoldingWorker);

        $this->transferValidationService->validateInventoryAccess($fromInventory);
        $this->transferValidationService->validateInventoryAccess($toInventory);

        $transferItem = [
            'item_id' => $production->product_part_id,
            'item_type' => 'App\Models\ProductPart',
            'color_id' => $production->color_id,
            'quantity' => $production->bunch_count,
            'notes' => "برای اتو زدن"
        ];

        $this->createAndApproveTransfer([
            'from_user_id' => $production->user_id,
            'to_user_id' => null,
            'from_inventory_id' => $fromInventory->id,
            'to_inventory_id' => $toInventory->id,
            'transfer_date' => $production->production_date,
            'description' => "حواله خودکار: تولید اتوکاری توسط {$production->user->firstname} {$production->user->lastname}",
            'items' => [$transferItem]
        ], $approver);
    }

    private function createAndApproveTransfer (array $data, User $approver)
    {
        $transfer = $this->transferCreationService->create($data);
        $this->transferApprovalService->approve($transfer, $approver);
    }

    private function getOrCreateInventoryByType(InventoryType $type): Inventory
    {
        return Inventory::firstOrCreate(
            ['type' => $type->value],
            [
                'name' => $type->label(),
                'description' => "انبار عمومی - {$type->label()}",
                'user_id' => null
            ]
        );
    }
}
