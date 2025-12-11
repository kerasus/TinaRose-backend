<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductPart;
use App\Models\ProductPartRequirement;
use App\Models\ProductRequirement;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductPartController extends Controller
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
            'filterKeys' => ['name', 'code']
        ];

        return $this->commonIndex($request, ProductPart::class, $config);
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
            'count_per_bunch' => 'required|integer|min:1'
        ]);

        return $this->commonStore($request, ProductPart::class);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $productPart = ProductPart::with('requirements.requiredItem')->findOrFail($id);

        return $this->jsonResponseOk($productPart);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param ProductPart $productPart
     * @return JsonResponse
     */
    public function update(Request $request, ProductPart $productPart): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'count_per_bunch' => 'sometimes|required|integer|min:1'
        ]);

        return $this->commonUpdate($request, $productPart);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param ProductPart $productPart
     * @return JsonResponse
     */
    public function destroy(ProductPart $productPart): JsonResponse
    {
        return $this->commonDestroy($productPart);
    }

    private function getTableFromItemType(string $itemType): string
    {
        $map = [
            'App\Models\Fabric' => 'fabrics',
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
    public function addRequirement(Request $request, ProductPart $productPart): JsonResponse
    {
        $request->validate([
            'required_item_type' => 'required|string|in:App\Models\Fabric',
            'required_item_id' => 'required|exists:' . $this->getTableFromItemType($request->required_item_type) . ',id',
            'quantity' => 'required|numeric',
            'unit' => 'required|string|max:50'
        ]);

        $requirement = ProductPartRequirement::create([
            'product_part_id' => $productPart->id,
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
    public function updateRequirement(Request $request, ProductPart $productPart, ProductPartRequirement $requirement): JsonResponse
    {
        if ($requirement->product_part_id !== $productPart->id) {
            return response()->json([
                'message' => 'خطا',
                'errors' => [
                    'product_part_id' => 'این نیازمندی متعلق به این محصول نیست.'
                ]
            ], 403);
        }

        $request->validate([
            'required_item_type' => 'sometimes|required|string|in:App\Models\Fabric',
            'required_item_id' => 'sometimes|required|exists:' . $this->getTableFromItemType($request->required_item_type ?? $requirement->required_item_type) . ',id',
            'quantity' => 'sometimes|required|numeric',
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
    public function removeRequirement(ProductPart $productPart, ProductPartRequirement $requirement): JsonResponse
    {
        if ($requirement->product_part_id !== $productPart->id) {
            return response()->json([
                'message' => 'خطا',
                'errors' => [
                    'product_part_id' => 'این نیازمندی متعلق به این زیر محصول نیست.'
                ]
            ], 403);
        }

        $requirement->delete();

        return response()->json(null, 204);
    }
}
