<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Traits\Filter;
use App\Models\Inventory;
use App\Traits\CommonCRUD;
use Illuminate\Http\Request;
use App\Models\InventoryItem;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;

class InventoryController extends Controller
{
    use Filter, CommonCRUD;

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => ['name'],
            'filterOrKeysExact' => [
                'id',
                'user_id',
                'type'
            ],
            'eagerLoads' => [
                'user'
            ]
        ];

        return $this->commonIndex($request, Inventory::class, $config);
    }

    public function inventoryItems(Request $request, $inventory)
    {
        $request->merge([
            'inventory_id' => $inventory
        ]);

        $config = [
            'filterKeysExact' => [
                'inventory_id',
                'item_type',
                'item_id'
            ],
            'eagerLoads' => [
                'color',
                'item'
            ],
            'scopes'=> [
                'nonZero'
            ],
        ];

        return $this->commonIndex($request, InventoryItem::class, $config);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $color = Inventory::findOrFail($id);

        return $this->jsonResponseOk($color);
    }

    /**
     * Initialize default inventories for all types and assemblers.
     * Safe to call multiple times.
     *
     * @return JsonResponse
     */
    public function initializeInventories(): JsonResponse
    {
        $created = [];
        $alreadyExist = [];

        $types = [
            'fabric_cutter' => 'دسته برش کاری',
            'coloring_worker' => 'دسته رنگکاری',
            'molding_worker' => 'دسته اتو کاری',
//            'assembler' => 'دسته مونتاژ کاری',
            'central_warehouse' => 'دسته انبار مرکزی'
        ];

        foreach ($types as $type => $name) {
            $inventory = Inventory::firstOrCreate(
                ['type' => $type], // 🔍 فقط بر اساس type چک می‌کنه
                [
                    'name' => $name,
                    'description' => "انبار عمومی - {$name}",
                    'user_id' => null
                ]
            );

            if ($inventory->wasRecentlyCreated) {
                $created[] = "انبار عمومی «{$name}» ایجاد شد.";
            } else {
                $alreadyExist[] = "انبار عمومی «{$name}» از قبل وجود داشت.";
            }
        }

        $assemblers = User::role('assembler')->get();

        foreach ($assemblers as $user) {
            $inventory = Inventory::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => "مونتاژ کاری ({$user->firstname} {$user->lastname})",
                    'description' => "انبار شخصی {$user->firstname} {$user->lastname}",
                    'type' => 'assembler',
                    'user_id' => $user->id
                ]
            );

            if ($inventory->wasRecentlyCreated) {
                $created[] = "انبار شخصی برای «{$user->firstname} {$user->lastname}» ایجاد شد.";
            } else {
                $alreadyExist[] = "انبار شخصی برای «{$user->firstname} {$user->lastname}» از قبل وجود داشت.";
            }
        }

        return response()->json([
            'message' => 'انبارهای اولیه بررسی و در صورت نیاز ایجاد شدند.',
            'created' => $created,
            'already_exist' => $alreadyExist,
            'total_created' => count($created),
            'total_existing' => count($alreadyExist)
        ]);
    }

    /**
     * Remove the specified inventory item if it belongs to the inventory and quantity is zero.
     *
     * @param int $inventoryId
     * @param int $inventoryItemId
     * @return JsonResponse
     */
    public function destroyInventoryItem(int $inventoryId, int $inventoryItemId): JsonResponse
    {
        Inventory::findOrFail($inventoryId);

        $inventoryItem = InventoryItem::where([
            ['id', $inventoryItemId],
            ['inventory_id', $inventoryId]
        ])->first();

        if (!$inventoryItem) {
            return response()->json([
                'errors' => [
                    'inventory_item' => 'آیتم مورد نظر در این انبار یافت نشد.'
                ]
            ], 404);
        }

        if ($inventoryItem->quantity > 0) {
            return response()->json([
                'errors' => [
                    'quantity' => 'فقط آیتم‌هایی با موجودی صفر قابل حذف هستند.'
                ]
            ], 422);
        }

        $inventoryItem->delete();

        return response()->json([
            'message' => 'آیتم انبار با موفقیت حذف شد.'
        ]);
    }
}
