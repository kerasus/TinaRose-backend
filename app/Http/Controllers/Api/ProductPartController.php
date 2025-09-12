<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductPart;
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
            'filterKeys' => ['name']
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
        $productPart = ProductPart::findOrFail($id);

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
}
