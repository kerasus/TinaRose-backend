<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Correspondence\InboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CorrespondenceInboxController extends Controller
{
    public function __construct(private readonly InboxService $inboxService)
    {
    }

    public function recipients(Request $request): JsonResponse
    {
        $perPage = $request->has('length') ? (int) $request->get('length') : 20;
        $items = $this->inboxService->inbox($request->user(), $request, $perPage);
        return response()->json($items);
    }

    public function assignments(Request $request): JsonResponse
    {
        $perPage = $request->has('length') ? (int) $request->get('length') : 20;
        $items = $this->inboxService->assignments($request->user(), $request, $perPage);
        return response()->json($items);
    }
}
