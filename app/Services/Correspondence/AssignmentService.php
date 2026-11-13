<?php

namespace App\Services\Correspondence;

use App\Enums\Correspondence\AssignmentStatus;
use App\Enums\Correspondence\CorrespondenceEventType;
use App\Models\CorrespondenceAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentService
{
    public function __construct(
        private readonly CorrespondenceService $correspondenceService
    ) {
    }

    public function createChild(CorrespondenceAssignment $parent, User $actor, array $data): CorrespondenceAssignment
    {
        if ($parent->assigned_to_id !== $actor->id) {
            throw new AuthorizationException('فقط مسئول ارجاع والد می‌تواند ارجاع فرزند ایجاد کند.');
        }

        if (!in_array($parent->status, [AssignmentStatus::PENDING, AssignmentStatus::IN_PROGRESS], true)) {
            throw ValidationException::withMessages(['parent_assignment' => 'ارجاع والد قابل ارجاع مجدد نیست.']);
        }

        return DB::transaction(function () use ($parent, $actor, $data) {
            $child = CorrespondenceAssignment::create([
                'correspondence_id' => $parent->correspondence_id,
                'parent_assignment_id' => $parent->id,
                'assigned_by_id' => $actor->id,
                'assigned_to_id' => $data['assigned_to_id'],
                'note' => $data['note'] ?? null,
                'due_at' => $data['due_at'] ?? null,
                'status' => AssignmentStatus::PENDING,
            ]);

            $this->correspondenceService->logEvent(
                $parent->correspondence,
                $actor,
                CorrespondenceEventType::ASSIGNED,
                $child,
                [
                    'assigned_to_id' => $child->assigned_to_id,
                    'parent_assignment_id' => $parent->id,
                ]
            );

            return $child->load(['assignedBy', 'assignedTo', 'parent']);
        });
    }

    public function start(CorrespondenceAssignment $assignment, User $actor): CorrespondenceAssignment
    {
        $this->assertOwner($assignment, $actor);
        if ($assignment->status !== AssignmentStatus::PENDING) {
            throw ValidationException::withMessages(['status' => 'فقط ارجاع در انتظار قابل شروع است.']);
        }

        return DB::transaction(function () use ($assignment, $actor) {
            $assignment->update([
                'status' => AssignmentStatus::IN_PROGRESS,
                'started_at' => now(),
                'read_at' => $assignment->read_at ?? now(),
            ]);
            $this->correspondenceService->logEvent($assignment->correspondence, $actor, CorrespondenceEventType::STARTED, $assignment);
            return $assignment->fresh();
        });
    }

    public function complete(CorrespondenceAssignment $assignment, User $actor, ?string $note = null): CorrespondenceAssignment
    {
        $this->assertOwner($assignment, $actor);
        if ($assignment->status !== AssignmentStatus::IN_PROGRESS) {
            throw ValidationException::withMessages(['status' => 'برای تکمیل، ابتدا باید ارجاع را شروع کنید.']);
        }

        return DB::transaction(function () use ($assignment, $actor, $note) {
            $assignment->update([
                'status' => AssignmentStatus::COMPLETED,
                'resolved_at' => now(),
                'resolution_note' => $note,
            ]);
            $this->correspondenceService->logEvent($assignment->correspondence, $actor, CorrespondenceEventType::COMPLETED, $assignment, [
                'resolution_note' => $note,
            ]);
            return $assignment->fresh();
        });
    }

    public function reject(CorrespondenceAssignment $assignment, User $actor, ?string $note = null): CorrespondenceAssignment
    {
        $this->assertOwner($assignment, $actor);
        if (!in_array($assignment->status, [AssignmentStatus::PENDING, AssignmentStatus::IN_PROGRESS], true)) {
            throw ValidationException::withMessages(['status' => 'وضعیت ارجاع اجازه رد را نمی‌دهد.']);
        }

        return DB::transaction(function () use ($assignment, $actor, $note) {
            $assignment->update([
                'status' => AssignmentStatus::REJECTED,
                'resolved_at' => now(),
                'resolution_note' => $note,
            ]);
            $this->correspondenceService->logEvent($assignment->correspondence, $actor, CorrespondenceEventType::REJECTED, $assignment, [
                'resolution_note' => $note,
            ]);
            return $assignment->fresh();
        });
    }

    private function assertOwner(CorrespondenceAssignment $assignment, User $actor): void
    {
        if ($assignment->assigned_to_id !== $actor->id) {
            throw new AuthorizationException('فقط مسئول ارجاع می‌تواند این عملیات را انجام دهد.');
        }
    }
}
