<?php

namespace App\Http\Controllers\Api;

use App\Models\Color;
use App\Traits\Filter;
use App\Traits\CommonCRUD;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class ColorController extends Controller
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

        return $this->commonIndex($request, Color::class, $config);
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
            'color_hex' => 'nullable|string|max:10',
        ]);

        return $this->commonStore($request, Color::class);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $color = Color::findOrFail($id);

        return $this->jsonResponseOk($color);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param Color $color
     * @return JsonResponse
     */
    public function update(Request $request, Color $color): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'color_hex' => 'nullable|nullable|string|max:10'
        ]);

        \Log::info('Model Data', [
            'model_exists' => $color->exists,
            'model_id' => $color->id,
            'model_data' => $color->toArray()
        ]);

        return $this->commonUpdate($request, $color);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Color $color
     * @return JsonResponse
     */
    public function destroy(Color $color): JsonResponse
    {
        return $this->commonDestroy($color);
    }
}
