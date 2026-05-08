<?php

namespace App\Http\Controllers\Api;

use App\Models\DowntimeReason;
use App\Traits\Filter;
use App\Traits\CommonCRUD;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class DowntimeReasonController extends Controller
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
            'filterKeys' => ['name', 'description']
        ];

        return $this->commonIndex($request, DowntimeReason::class, $config);
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
            'description' => 'nullable|string',
        ]);

        return $this->commonStore($request, DowntimeReason::class);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $downtimeReason = DowntimeReason::findOrFail($id);

        return $this->jsonResponseOk($downtimeReason);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param DowntimeReason $downtimeReason
     * @return JsonResponse
     */
    public function update(Request $request, DowntimeReason $downtimeReason): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        \Log::info('Model Data', [
            'model_exists' => $downtimeReason->exists,
            'model_id' => $downtimeReason->id,
            'model_data' => $downtimeReason->toArray()
        ]);

        return $this->commonUpdate($request, $downtimeReason);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DowntimeReason $downtimeReason
     * @return JsonResponse
     */
    public function destroy(DowntimeReason $downtimeReason): JsonResponse
    {
        return $this->commonDestroy($downtimeReason);
    }
}
