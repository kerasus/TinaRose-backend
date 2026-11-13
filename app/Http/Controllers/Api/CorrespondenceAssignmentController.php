<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Correspondence\CreateAssignmentRequest;
use App\Http\Requests\Correspondence\ResolveAssignmentRequest;
use App\Http\Resources\Correspondence\CorrespondenceAssignmentResource;
use App\Models\CorrespondenceAssignment;
use App\Services\Correspondence\AssignmentService;
use App\Services\Correspondence\InboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CorrespondenceAssignmentController extends Controller
{
    public function __construct(
        private readonly AssignmentService $assignmentService,
        private readonly InboxService $inboxService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $items = $this->inboxService->assignments($request->user());
        return response()->json($items);
    }

    public function store(CreateAssignmentRequest $request, CorrespondenceAssignment $assignment): JsonResponse
    {
        // $assignment is the parent assignment (route-model binding)
        $child = $this->assignmentService->createChild($assignment, $request->user(), $request->validated());

        return response()->json(new CorrespondenceAssignmentResource($child), 201);
    }

    public function start(Request $request, CorrespondenceAssignment $assignment): JsonResponse
    {
        $updated = $this->assignmentService->start($assignment, $request->user());
        return response()->json(new CorrespondenceAssignmentResource($updated));
    }

    public function complete(ResolveAssignmentRequest $request, CorrespondenceAssignment $assignment): JsonResponse
    {
        $updated = $this->assignmentService->complete($assignment, $request->user(), $request->validated('resolution_note'));
        return response()->json(new CorrespondenceAssignmentResource($updated));
    }

    public function reject(ResolveAssignmentRequest $request, CorrespondenceAssignment $assignment): JsonResponse
    {
        $updated = $this->assignmentService->reject($assignment, $request->user(), $request->validated('resolution_note'));
        return response()->json(new CorrespondenceAssignmentResource($updated));
    }
}
