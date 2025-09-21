<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fabric;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FabricController extends Controller
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
            'filterKeys' => ['name', 'code', 'color_hex']
        ];

        return $this->commonIndex($request, Fabric::class, $config);
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
            'color_hex' => 'nullable|string|max:10'
        ]);

        return $this->commonStore($request, Fabric::class);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $fabric = Fabric::findOrFail($id);

        return $this->jsonResponseOk($fabric);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param Fabric $fabric
     * @return JsonResponse
     */
    public function update(Request $request, Fabric $fabric): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'color_hex' => 'sometimes|nullable|string|max:10'
        ]);

        return $this->commonUpdate($request, $fabric);
    }

    /**
     * Remove the specified resource from storage.
     * @param Fabric $fabric
     * @return JsonResponse
     */
    public function destroy(Fabric $fabric): JsonResponse
    {
        return $this->commonDestroy($fabric);
    }
}
