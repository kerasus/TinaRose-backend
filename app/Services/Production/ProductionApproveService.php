<?php

namespace App\Services\Production;

use App\Models\InventoryCount;
use App\Models\InventoryItem;
use App\Services\Inventory\InventoryCountFinalizeService;
use Throwable;
use App\Models\User;
use App\Models\Transfer;
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
    private TransferValidationService $validationService;
    private TransferApprovalService $transferApprovalService;
    private InventoryCountFinalizeService $inventoryCountFinalizeService;

    public function __construct(
        TransferCreationService $transferCreationService,
        TransferValidationService $validationService,
        TransferApprovalService $transferApprovalService,
        InventoryCountFinalizeService $inventoryCountFinalizeService,
    ) {
        $this->transferCreationService = $transferCreationService;
        $this->validationService = $validationService;
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

        // چک کردن نیازمندی‌ها فقط برای FabricCutter
        $user = $production->user;
        $userRoles = $user->getRoleNames();

        if ($userRoles->contains(UserRoleType::FabricCutter->value)) {
            $this->validateFabricCutterRequirements($production);
        }

        // تأیید تولید
        $production->update([
            'approved_by' => $approver->id,
            'approved_at' => now()
        ]);

        // ایجاد حواله خودکار
        if ($userRoles->contains(UserRoleType::FabricCutter->value)) {
            $this->createTransferForFabricCutter($production, $approver);
        } elseif ($userRoles->contains(UserRoleType::ColoringWorker->value)) {
            $this->createTransferForColoringWorker($production, $approver);
        } elseif ($userRoles->contains(UserRoleType::MoldingWorker->value)) {
            $this->createTransferForMoldingWorker($production, $approver);
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateFabricCutterRequirements(Production $production): void
    {
        $fromInventory = $this->getOrCreateInventoryByType(InventoryType::CentralWarehouse);

        $productPart = $production->productPart;

        foreach ($productPart->requirements as $requirement) {
            $requiredQuantity = $requirement->quantity * $production->bunch_count;

            $itemData = [
                'item_id' => $requirement->required_item_id,
                'item_type' => $requirement->required_item_type,
                'color_id' => null,
                'quantity' => $requiredQuantity
            ];

            $this->validationService->validateInventoryAvailability([$itemData], $fromInventory);
        }
    }

    private function createTransferForFabricCutter(Production $production, User $approver): void
    {
        $fromInventory = $this->getOrCreateInventoryByType(InventoryType::CentralWarehouse);
        $toInventory = $this->getOrCreateInventoryByType(InventoryType::FabricCutter);

        $productPart = $production->productPart;

        // شناسایی نیازمندی های تولید
        $transferItems = [];
        foreach ($productPart->requirements as $requirement) {
            $requiredQuantity = $production->bunch_count * $productPart->count_per_bunch * $requirement->quantity;

            $transferItems[] = [
                'item_id' => $requirement->required_item_id,
                'item_type' => $requirement->required_item_type,
                'color_id' => null,
                'quantity' => $requiredQuantity,
                'notes' => "برای تولید {$productPart->name}"
            ];
        }

        // انتقال نیازمندی های تولید به انبار برشکاری
        $this->createAndApproveTransfer([
            'from_user_id' => null,
            'to_user_id' => $production->user_id,
            'from_inventory_id' => $fromInventory->id,
            'to_inventory_id' => $toInventory->id,
            'transfer_date' => $production->production_date,
            'description' => "حواله خودکار: تولید برشکاری توسط {$production->user->firstname} {$production->user->lastname}",
            'items' => $transferItems
        ], $approver);

        // انبارگردانی: تبدیل نیازمندی به زیرمحصول
        $count = InventoryCount::create([
            'inventory_id' => $toInventory->id,
            'count_date' => now(),
            'counter_user_id' => $approver->id,
            'notes' => "انبارگردانی سیستمی: تولید {$productPart->name} از نیازمندی‌ها",
        ]);
        // ثبت تغییرات نیازمندی‌ها (کاهش)
        foreach ($transferItems as $item) {
            $inventoryItem = InventoryItem::where([
                ['inventory_id', $toInventory->id],
                ['item_id', $item['item_id']],
                ['item_type', $item['item_type']],
                ['color_id', $item['color_id']]
            ])->first();

            $systemQuantity = $inventoryItem?->quantity ?? 0;
            $actualQuantity = $systemQuantity - $item['quantity']; // کاهش نیازمندی
            $difference = -$item['quantity'];

            $count->items()->create([
                'item_id' => $item['item_id'],
                'item_type' => $item['item_type'],
                'color_id' => $item['color_id'],
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
            ['color_id', null]
        ])->first();

        $systemQuantity = $inventoryItem?->quantity ?? 0;
        $producedQuantity = $production->bunch_count * $productPart->count_per_bunch; // تعداد گلبرگ
        $actualQuantity = $systemQuantity + $producedQuantity; // افزایش زیرمحصول
        $difference = $producedQuantity;

        $count->items()->create([
            'item_id' => $productPart->id,
            'item_type' => 'App\Models\ProductPart',
            'color_id' => null,
            'system_quantity' => $systemQuantity,
            'actual_quantity' => $actualQuantity,
            'difference' => $difference
        ]);

        $this->inventoryCountFinalizeService->finalize($count, true);
    }

    private function createTransferForColoringWorker(Production $production, User $approver): void
    {
        $fromInventory = $this->getOrCreateInventoryByType(InventoryType::FabricCutter);
        $toInventory = $this->getOrCreateInventoryByType(InventoryType::ColoringWorker);

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

    private function createTransferForMoldingWorker(Production $production, User $approver): void
    {
        $fromInventory = $this->getOrCreateInventoryByType(InventoryType::ColoringWorker);
        $toInventory = $this->getOrCreateInventoryByType(InventoryType::MoldingWorker);

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
