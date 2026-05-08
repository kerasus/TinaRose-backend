<?php

namespace App\Http\Controllers\Api;

use App\Models\Equipment;
use App\Traits\Filter;
use App\Traits\CommonCRUD;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class EquipmentController extends Controller
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
            'filterKeys' => ['title', 'code', 'description']
        ];

        return $this->commonIndex($request, Equipment::class, $config);
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
            'title' => 'required|string|max:255',
            'code' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        return $this->commonStore($request, Equipment::class);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $equipment = Equipment::findOrFail($id);

        return $this->jsonResponseOk($equipment);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param Equipment $equipment
     * @return JsonResponse
     */
    public function update(Request $request, Equipment $equipment): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'code' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        \Log::info('Model Data', [
            'model_exists' => $equipment->exists,
            'model_id' => $equipment->id,
            'model_data' => $equipment->toArray()
        ]);

        return $this->commonUpdate($request, $equipment);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Equipment $equipment
     * @return JsonResponse
     */
    public function destroy(Equipment $equipment): JsonResponse
    {
        return $this->commonDestroy($equipment);
    }
}
