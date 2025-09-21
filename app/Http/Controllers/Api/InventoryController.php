<?php

namespace App\Http\Controllers\Api;

use App\Models\InventoryItem;
use App\Traits\Filter;
use App\Models\Inventory;
use App\Traits\CommonCRUD;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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
}
