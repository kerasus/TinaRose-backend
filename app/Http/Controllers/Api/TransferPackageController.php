<?php

namespace App\Http\Controllers\Api;

use App\Models\Color;
use App\Models\Product;
use App\Models\ProductPart;
use App\Models\RawMaterial;
use App\Models\TransferPackage;
use App\Traits\Filter;
use App\Traits\CommonCRUD;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TransferPackageController extends Controller
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
            'filterKeys' => ['name', 'description'],
            'filterKeysExact' => [],
            'eagerLoads' => ['items.item', 'items.color']
        ];

        return $this->commonIndex($request, TransferPackage::class, $config);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'items'             => 'required|array|min:1',
            'items.*.item_type' => [
                'required',
                'string',
                Rule::in([
                    ProductPart::class,
                    RawMaterial::class,
                    Product::class
                ])
            ],
            'items.*.item_id' => 'required|integer',
            'items.*.color_id'  => 'nullable|exists:colors,id',
            'items.*.quantity'  => 'required|numeric|min:0.01',
            'items.*.notes'     => 'nullable|string'
        ]);

        // --- ولیدیشن داینامیک ---
        foreach ($request->items as $index => $item) {
            $itemType = $item['item_type'] ?? null;
            $itemId = $item['item_id'] ?? null;
            $colorId = $item['color_id'] ?? null;
            $rowCounter = $index + 1;

            if (!class_exists($itemType)) {
                return response()->json([
                    'errors' => [
                        'validate_items' => "نوع آیتم نامعتبر در ردیف {$rowCounter}: {$itemType}"
                    ]
                ], 422);
            }

            if (!is_subclass_of($itemType, \Illuminate\Database\Eloquent\Model::class)) {
                return response()->json([
                    'errors' => [
                        'validate_items' => "مدل ارث‌برده از Eloquent نیست در ردیف {$rowCounter}: {$itemType}"
                    ]
                ], 422);
            }

            $exists = $itemType::where('id', $itemId)->exists();
            if (!$exists) {
                return response()->json([
                    'errors' => [
                        'validate_items' => "آیتم با شناسه {$itemId} در مدل {$itemType} یافت نشد (ردیف {$rowCounter})."
                    ]
                ], 422);
            }

            if ($colorId && !Color::find($colorId)) {
                return response()->json([
                    'errors' => [
                        'validate_items' => "رنگ با شناسه {$colorId} یافت نشد (ردیف {$rowCounter})."
                    ]
                ], 422);
            }
        }

        try {
            $package = DB::transaction(function () use ($request) {

                $package = TransferPackage::create($request->only(['name', 'description']));

                foreach ($request->items as $item) {
                    $package->items()->create($item);
                }

                return $package;
            });

            return $this->show($package->id);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در ایجاد پک حواله',
                'errors' => [
                    $e->getMessage()
                ]
            ], 422);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $package = TransferPackage::with(['items.item', 'items.color'])->findOrFail($id);

        return $this->jsonResponseOk($package);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param TransferPackage $package
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'items' => 'sometimes|required|array|min:1',
            'items.*.item_type' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    ProductPart::class,
                    RawMaterial::class,
                    Product::class
                ])
            ],
            'items.*.item_id' => 'required|integer',
            'items.*.color_id' => 'sometimes|nullable|exists:colors,id',
            'items.*.quantity' => 'sometimes|required|numeric|min:0.01',
            'items.*.notes' => 'sometimes|nullable|string'
        ]);

        $package = TransferPackage::with(['items'])->findOrFail($id);

        // --- ولیدیشن داینامیک ---
        foreach ($request->items as $index => $item) {
            $itemType = $item['item_type'] ?? null;
            $itemId = $item['item_id'] ?? null;
            $colorId = $item['color_id'] ?? null;
            $rowCounter = $index + 1;

            if (!class_exists($itemType)) {
                return response()->json([
                    'errors' => [
                        'validate_items' => "نوع آیتم نامعتبر در ردیف {$rowCounter}: {$itemType}"
                    ]
                ], 422);
            }

            if (!is_subclass_of($itemType, \Illuminate\Database\Eloquent\Model::class)) {
                return response()->json([
                    'errors' => [
                        'validate_items' => "مدل ارث‌برده از Eloquent نیست در ردیف {$rowCounter}: {$itemType}"
                    ]
                ], 422);
            }

            $exists = $itemType::where('id', $itemId)->exists();
            if (!$exists) {
                return response()->json([
                    'errors' => [
                        'validate_items' => "آیتم با شناسه {$itemId} در مدل {$itemType} یافت نشد (ردیف {$rowCounter})."
                    ]
                ], 422);
            }

            if ($colorId && !Color::find($colorId)) {
                return response()->json([
                    'errors' => [
                        'validate_items' => "رنگ با شناسه {$colorId} یافت نشد (ردیف {$rowCounter})."
                    ]
                ], 422);
            }
        }

        try {
            $package->update($request->only(['name', 'description']));

            if ($request->filled('items')) {
                $package->items()->delete();

                foreach ($request->items as $item) {
                    unset($item['id']);
                    unset($item['item']);
                    unset($item['color']);
                    unset($item['created_at']);
                    unset($item['updated_at']);

                    $item['transfer_package_id'] = $package->id;

                    $package->items()->create($item);
                }
            }

            return $this->show($package->id);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در ویرایش پک حواله',
                'errors' => [
                    $e->getMessage()
                ]
            ], 422);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param TransferPackage $package
     * @return JsonResponse
     */
    public function destroy(TransferPackage $package): JsonResponse
    {
        return $this->commonDestroy($package);
    }
}
