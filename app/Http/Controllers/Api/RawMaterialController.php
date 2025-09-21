<?php

namespace App\Http\Controllers\Api;

use App\Traits\Filter;
use App\Traits\CommonCRUD;
use App\Models\RawMaterial;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class RawMaterialController extends Controller
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
            'filterKeys' => ['name', 'code'],
            'filterKeysExact' => ['unit_small', 'unit_large']
        ];

        return $this->commonIndex($request, RawMaterial::class, $config);
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
            'name' => 'required|string|max:255',
            'unit_large' => 'nullable|string|max:50',
            'unit_small' => 'required|string|max:50',
            'conversion_rate' => 'nullable|integer|min:1',
            'initial_stock' => 'nullable|numeric|min:0',
            'current_stock' => 'nullable|numeric|min:0'
        ]);

        return $this->commonStore($request, RawMaterial::class);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $rawMaterial = RawMaterial::findOrFail($id);

        return $this->jsonResponseOk($rawMaterial);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param RawMaterial $rawMaterial
     * @return JsonResponse
     */
    public function update(Request $request, RawMaterial $rawMaterial): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'unit_large' => 'sometimes|nullable|string|max:50',
            'unit_small' => 'sometimes|required|string|max:50',
            'conversion_rate' => 'sometimes|nullable|integer|min:1',
            'initial_stock' => 'sometimes|nullable|numeric|min:0',
            'current_stock' => 'sometimes|nullable|numeric|min:0'
        ]);

        return $this->commonUpdate($request, $rawMaterial);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param RawMaterial $rawMaterial
     * @return JsonResponse
     */
    public function destroy(RawMaterial $rawMaterial): JsonResponse
    {
        return $this->commonDestroy($rawMaterial);
    }
}
