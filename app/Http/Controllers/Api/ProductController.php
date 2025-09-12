<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Traits\Filter;
use App\Traits\CommonCRUD;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\ProductRequirement;
use App\Http\Controllers\Controller;

class ProductController extends Controller
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
            'filterKeysExact' => ['unit_small', 'unit_large'],
            'eagerLoads' => ['requirements.requiredItem']
        ];

        return $this->commonIndex($request, Product::class, $config);
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

        return $this->commonStore($request, Product::class);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::with('requirements.requiredItem')->findOrFail($id);

        return $this->jsonResponseOk($product);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param Product $product
     * @return JsonResponse
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'unit_large' => 'sometimes|nullable|string|max:50',
            'unit_small' => 'sometimes|required|string|max:50',
            'conversion_rate' => 'sometimes|nullable|integer|min:1',
            'initial_stock' => 'sometimes|nullable|numeric|min:0',
            'current_stock' => 'sometimes|nullable|numeric|min:0'
        ]);

        return $this->commonUpdate($request, $product);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Product $product
     * @return JsonResponse
     */
    public function destroy(Product $product): JsonResponse
    {
        return $this->commonDestroy($product);
    }

    private function getTableFromItemType(string $itemType): string
    {
        $map = [
            'App\Models\ProductPart' => 'product_parts',
            'App\Models\RawMaterial' => 'raw_materials'
        ];

        return $map[$itemType] ?? throw new \InvalidArgumentException('Invalid item type');
    }

    /**
     * Add a new requirement to the product.
     *
     * @param Request $request
     * @param Product $product
     * @return JsonResponse
     */
    public function addRequirement(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'required_item_type' => 'required|string|in:App\Models\ProductPart,App\Models\RawMaterial',
            'required_item_id' => 'required|exists:' . $this->getTableFromItemType($request->required_item_type) . ',id',
            'quantity' => 'required|numeric|min:0.01',
            'unit' => 'required|string|max:50'
        ]);

        $requirement = ProductRequirement::create([
            'product_id' => $product->id,
            'required_item_type' => $request->required_item_type,
            'required_item_id' => $request->required_item_id,
            'quantity' => $request->quantity,
            'unit' => $request->unit
        ]);

        return $this->jsonResponseOk($requirement->load('requiredItem'));
    }

    /**
     * Update a requirement of the product.
     *
     * @param Request $request
     * @param Product $product
     * @param ProductRequirement $requirement
     * @return JsonResponse
     */
    public function updateRequirement(Request $request, Product $product, ProductRequirement $requirement): JsonResponse
    {
        if ($requirement->product_id !== $product->id) {
            return response()->json(['error' => 'این نیازمندی متعلق به این محصول نیست.'], 403);
        }

        $request->validate([
            'required_item_type' => 'sometimes|required|string|in:App\Models\ProductPart,App\Models\RawMaterial',
            'required_item_id' => 'sometimes|required|exists:' . $this->getTableFromItemType($request->required_item_type ?? $requirement->required_item_type) . ',id',
            'quantity' => 'sometimes|required|numeric|min:0.01',
            'unit' => 'sometimes|required|string|max:50'
        ]);

        $requirement->update($request->only(['required_item_id', 'required_item_type', 'quantity', 'unit']));

        return $this->jsonResponseOk($requirement->refresh()->load('requiredItem'));
    }

    /**
     * Remove a requirement from the product.
     *
     * @param Product $product
     * @param ProductRequirement $requirement
     * @return JsonResponse
     */
    public function removeRequirement(Product $product, ProductRequirement $requirement): JsonResponse
    {
        if ($requirement->product_id !== $product->id) {
            return response()->json(['error' => 'این نیازمندی متعلق به این محصول نیست.'], 403);
        }

        $requirement->delete();

        return response()->json(null, 204);
    }
}
