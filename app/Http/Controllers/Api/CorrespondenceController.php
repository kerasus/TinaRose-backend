<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Correspondence\AddRecipientsRequest;
use App\Http\Requests\Correspondence\StoreAttachmentRequest;
use App\Http\Requests\Correspondence\StoreCorrespondenceRequest;
use App\Http\Resources\Correspondence\CorrespondenceResource;
use App\Models\Color;
use App\Models\Correspondence;
use App\Services\Correspondence\AttachmentService;
use App\Services\Correspondence\CorrespondenceService;
use App\Traits\Filter;
use App\Traits\CommonCRUD;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CorrespondenceController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct(
        private readonly CorrespondenceService $correspondenceService,
        private readonly AttachmentService $attachmentService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $config = [
            'filterKeys' => ['reference_number', 'subject', 'body'],
            'filterKeysExact' => ['id', 'sender_id', 'status', 'priority', 'confidentiality'],
            'filterOrKeysExact' => ['status', 'priority', 'confidentiality'],
            'filterRelationIds' => [
                [
                    'requestKey' => 'recipient_id',
                    'relationName' => 'recipients',
                ],
            ],
            'filterDate' => ['created_at'],
            'eagerLoads' => [
                'sender',
                'recipients.user',
                'assignments.assignedBy',
                'assignments.assignedTo',
                'assignments.parent.assignedBy',
                'assignments.parent.assignedTo',
                'assignments.children.assignedBy',
                'assignments.children.assignedTo',
                'attachments.uploadedBy',
                'events.actor',
            ],
            'returnModelQuery' => true,
        ];

        $result = $this->commonIndex($request, Correspondence::class, $config);
        $modelQuery = $result['modelQuery'];

        $modelQuery->where(function ($q) use ($user) {
            $q->where('sender_id', $user->id)
                ->orWhereHas('recipients', fn ($r) => $r->where('user_id', $user->id))
                ->orWhereHas('assignments', fn ($a) => $a->where('assigned_to_id', $user->id));
        });

        $responseWithAttachedCollection = $result['responseWithAttachedCollection'];

        return $responseWithAttachedCollection($modelQuery);
    }

    public function store(StoreCorrespondenceRequest $request): JsonResponse
    {
        $sendImmediately = $request->boolean('send');
        $data = $request->validated();

        $correspondence = $sendImmediately
            ? $this->correspondenceService->createAndSend($request->user(), $data)
            : $this->correspondenceService->createDraft($request->user(), $data);

        return response()->json(new CorrespondenceResource($correspondence), 201);
    }

    public function show(Request $request, Correspondence $correspondence): JsonResponse
    {
        $this->authorize('view', $correspondence);
        $this->correspondenceService->markRead($correspondence, $request->user());

        $correspondence->load([
            'sender',
            'replyTo.sender',
            'recipients.user',
            'assignments.children',
            'assignments.assignedBy',
            'assignments.assignedTo',
            'assignments.parent.assignedBy',
            'assignments.parent.assignedTo',
            'attachments.uploadedBy',
            'events.actor',
        ]);

        return response()->json(new CorrespondenceResource($correspondence));
    }

    public function send(Request $request, Correspondence $correspondence): JsonResponse
    {
        $this->authorize('send', $correspondence);
        $sent = $this->correspondenceService->send($correspondence, $request->user());

        return response()->json(new CorrespondenceResource($sent));
    }

    public function destroy(Request $request, Correspondence $correspondence): JsonResponse
    {
        $this->authorize('delete', $correspondence);
        $this->correspondenceService->deleteIfUnseen($correspondence, $request->user());

        return response()->json(['message' => 'نامه با موفقیت حذف شد.']);
    }

    public function attach(StoreAttachmentRequest $request, Correspondence $correspondence): JsonResponse
    {
        $this->authorize('attach', $correspondence);
        $attachment = $this->attachmentService->store($correspondence, $request->file('file'), $request->user());

        return response()->json($attachment, 201);
    }

    public function addRecipients(AddRecipientsRequest $request, Correspondence $correspondence): JsonResponse
    {
        $this->authorize('addRecipient', $correspondence);
        $updated = $this->correspondenceService->addRecipients(
            $correspondence,
            $request->user(),
            $request->validated()['recipients']
        );

        return response()->json(new CorrespondenceResource($updated));
    }
}
