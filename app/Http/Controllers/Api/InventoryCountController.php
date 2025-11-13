<?php

namespace App\Http\Controllers\Api;

use App\Models\Color;
use App\Traits\Filter;
use App\Models\Product;
use App\Models\Inventory;
use App\Traits\CommonCRUD;
use App\Models\ProductPart;
use App\Models\RawMaterial;
use Illuminate\Http\Request;
use App\Models\InventoryItem;
use App\Models\InventoryCount;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use App\Models\InventoryCountItem;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class InventoryCountController extends Controller
{
    use Filter, CommonCRUD;

    public function index(Request $request)
    {
        $config = [
            'filterKeysExact' => ['inventory_id', 'counter_user_id'],
            'filterDate' => ['count_date'], // count_date_since_date, count_date_till_date
            'eagerLoads' => ['inventory', 'counter']
        ];

        return $this->commonIndex($request, InventoryCount::class, $config);
    }

    public function show(int $id)
    {
        $inventoryCount = InventoryCount::with(['inventory', 'counter', 'items.item', 'items.color'])->findOrFail($id);

        return $this->jsonResponseOk($inventoryCount);
    }

    /**
     * Get paginated list of inventory items with latest count data.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function getInventoryItemsPaginated(Request $request, int $id): JsonResponse
    {
        $inventoryCount = InventoryCount::findOrFail($id);
        $inventoryId = $inventoryCount->inventory_id;

        // ولیدیشن فیلترها
        $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'product_part_id' => 'nullable|exists:product_parts,id',
            'raw_material_id' => 'nullable|exists:raw_materials,id',
            'missing_actual' => 'nullable|string|in:true,false,1,0,on,off',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        $missingActual = filter_var($request->missing_actual, FILTER_VALIDATE_BOOLEAN);

        $perPage = $request->input('per_page', 10);

        // --- کوئری ۱: آیتم‌های موجود در انبار (inventory_items)
        $existingItems = InventoryItem::where('inventory_id', $inventoryId)
//            ->nonZero()
            ->with(['item', 'color'])
            ->leftJoin('inventory_count_items as ici', function ($join) use ($inventoryCount) {
                $join->on('ici.item_id', '=', 'inventory_items.item_id')
                    ->on('ici.item_type', '=', 'inventory_items.item_type')
                    ->on(DB::raw("COALESCE(ici.color_id, -1)"), '=', DB::raw("COALESCE(inventory_items.color_id, -1)"))
                    ->where('ici.inventory_count_id', '=', $inventoryCount->id);
            })
            ->select(
                'inventory_items.id as inventory_item_id',
                'inventory_items.inventory_id',
                'inventory_items.item_id',
                'inventory_items.item_type',
                'inventory_items.color_id',
                'inventory_items.quantity as system_quantity',
                'ici.actual_quantity as actual_quantity',
                'inventory_items.created_at',
                'inventory_items.updated_at',
                'ici.id as inventory_count_item_id',
                'inventory_items.notes as item_notes',
                'ici.notes as count_notes'
            );

        // --- کوئری ۲: آیتم‌های جدید (فقط در inventory_count_items)
        $newItems = InventoryCountItem::where('inventory_count_id', $inventoryCount->id)
            ->whereNotExists(function ($query) use ($inventoryId) {
                $query->select(DB::raw(1))
                    ->from('inventory_items')
                    ->where('inventory_id', $inventoryId)
                    ->whereColumn('inventory_items.item_id', 'inventory_count_items.item_id')
                    ->whereColumn('inventory_items.item_type', 'inventory_count_items.item_type')
                    ->where(DB::raw("COALESCE(inventory_items.color_id, -1)"), '=', DB::raw("COALESCE(inventory_count_items.color_id, -1)"));
            })
            ->leftJoin('inventory_items as ii', function ($join) use ($inventoryId) {
                $join->on('ii.item_id', '=', 'inventory_count_items.item_id')
                    ->on('ii.item_type', '=', 'inventory_count_items.item_type')
                    ->on(DB::raw("COALESCE(ii.color_id, -1)"), '=', DB::raw("COALESCE(inventory_count_items.color_id, -1)"))
                    ->where('ii.inventory_id', '=', $inventoryId);
            })
            ->with(['item', 'color'])
            ->select(
                DB::raw('NULL as inventory_item_id'),
                DB::raw('NULL as inventory_id'),
                'inventory_count_items.item_id',
                'inventory_count_items.item_type',
                'inventory_count_items.color_id',
                DB::raw('COALESCE(ii.quantity, 0) as system_quantity'),
                'inventory_count_items.actual_quantity as actual_quantity',
                'inventory_count_items.created_at',
                'inventory_count_items.updated_at',
                'inventory_count_items.id as inventory_count_item_id',
                DB::raw('NULL as item_notes'),
                'inventory_count_items.notes as count_notes'
            );

        // --- union دو کوئری
        $unionQuery = $existingItems->union($newItems);

        // --- اعمال فیلترها
        if ($request->filled('product_id')) {
            $unionQuery->where([
                ['inventory_items.item_type', '=', \App\Models\Product::class],
                ['inventory_items.item_id', '=', $request->product_id]
            ]);
        }

        if ($request->filled('product_part_id')) {
            $unionQuery->where([
                ['inventory_items.item_type', '=', \App\Models\ProductPart::class],
                ['inventory_items.item_id', '=', $request->product_part_id]
            ]);
        }

        if ($request->filled('raw_material_id')) {
            $unionQuery->where([
                ['inventory_items.item_type', '=', \App\Models\RawMaterial::class],
                ['inventory_items.item_id', '=', $request->raw_material_id]
            ]);
        }

        if ($request->filled('color_id')) {
            $unionQuery->where([
                ['inventory_items.color_id', '=', $request->color_id]
            ]);
        }

        if ($request->filled('missing_actual') && $missingActual === true) {
            $unionQuery->whereNull('ici.actual_quantity');
        }

        // --- صفحه‌بندی و مرتب‌سازی
        $results = $unionQuery->orderByRaw('actual_quantity IS NULL DESC')
            ->paginate($perPage);

        // --- تبدیل به فرمت مناسب
        $results->getCollection()->transform(function ($item) {
            return [
                'inventory_item_id' => $item->id, // ممکنه null باشه
                'inventory_count_item_id' => $item->inventory_count_item_id,
                'item' => $item->item,
                'item_type' => $item->item_type,
                'color' => $item->color,
                'system_quantity' => $item->system_quantity ?? 0,
                'actual_quantity' => $item->actual_quantity,
                'ii_quantity' => $item->ii_quantity,
                'count_notes' => $item->count_notes,
                'is_new' => $item->id === null // ✅ تشخیص آیتم جدید
            ];
        });

        return response()->json($results);
    }

    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'inventory_id' => 'required|exists:inventories,id',
            'count_date' => 'required|date',
            'counter_user_id' => 'nullable|exists:users,id'
        ]);

        $inventory = Inventory::with('items')->findOrFail($request->inventory_id);

        // ایجاد گزارش
        $count = InventoryCount::create($request->only(['inventory_id', 'count_date', 'counter_user_id', 'notes']));

        // پیش‌پر کردن آیتم‌ها با موجودی فعلی
        foreach ($inventory->items as $item) {
            $count->items()->create([
                'item_id' => $item->item_id,
                'item_type' => $item->item_type,
                'color_id' => $item->color_id,
                'system_quantity' => $item->quantity,
                'actual_quantity' => null,
                'difference' => -$item->quantity
            ]);
        }

        return response()->json($count->load('items.item', 'items.color'), 201);
    }

    public function updateItem(Request $request, int $countId): JsonResponse
    {
        $request->validate([
            'item_id' => 'required|integer',
            'item_type' => [
                'required',
                'string',
                Rule::in([ProductPart::class, RawMaterial::class, Product::class])
            ],
            'color_id' => 'nullable|exists:colors,id',
            'actual_quantity' => 'required|numeric|min:0',
            'notes' => 'nullable|string'
        ]);

        $count = InventoryCount::with(['items'])->findOrFail($countId);

        if ($count->is_locked) {
            return response()->json([
                'message' => 'خطا در ویرایش آیتم انبارگردانی',
                'errors' => [
                    'inventory' => 'این انبارگردانی قبلاً بسته شده است. امکان ویرایش آیتم‌ها وجود ندارد.'
                ]
            ], 422);
        }

        $inventoryItem = InventoryItem::where([
            ['inventory_id', $count->inventory_id],
            ['item_id', $request->item_id],
            ['item_type', $request->item_type],
            ['color_id', $request->color_id ?? null]
        ])->first();

        $systemQuantity = $inventoryItem?->quantity ?? 0;

        $item = InventoryCountItem::firstOrCreate(
            [
                'inventory_count_id' => $count->id,
                'item_id' => $request->item_id,
                'item_type' => $request->item_type,
                'color_id' => $request->color_id ?? null
            ],
            [
                'system_quantity' => $systemQuantity,
                'actual_quantity' => 0,
                'difference' => -$systemQuantity,
                'notes' => ''
            ]
        );

        $actual = $request->actual_quantity;
        $difference = $actual - $item->system_quantity;

        $item->update([
            'actual_quantity' => $actual,
            'difference' => $difference,
            'notes' => $request->notes
        ]);

        return response()->json(['message' => 'مقدار شمارش شده ذخیره شد.']);
    }

    public function finalize(Request $request, int $countId): JsonResponse
    {
        $count = InventoryCount::with(['items'])->findOrFail($countId);

        $hasMissingActual = $count->items()->whereNull('actual_quantity')->exists();

        if ($hasMissingActual) {
            return response()->json([
                'errors' => [
                    'hasMissingActual' => 'شمارش هنوز کامل انجام نشده. لطفاً تمام آیتم‌ها را شمارش کنید.'
                ]
            ], 422);
        }

        $adjust = $request->boolean('adjust_inventory', false);

        if ($adjust) {
            foreach ($count->items as $item) {
                if ($item->difference == 0) continue;

                $inventoryItem = InventoryItem::where([
                    ['inventory_id', $count->inventory_id],
                    ['item_id', $item->item_id],
                    ['item_type', $item->item_type],
                    ['color_id', $item->color_id]
                ])->first();

                $inventoryItem?->update(['quantity' => $item->actual_quantity]);
            }
        }

        $count->update(['is_locked' => true]);

        return response()->json([
            'message' => 'انبارگردانی با موفقیت بسته شد.',
            'adjusted_inventory' => $adjust
        ]);
    }

    public function destroy(InventoryCount $inventoryCount): JsonResponse
    {
        return $this->commonDestroy($inventoryCount);
    }
}
