<?php

namespace App\Services\Correspondence;

use App\Enums\Correspondence\AssignmentStatus;
use App\Enums\Correspondence\CorrespondenceEventType;
use App\Enums\Correspondence\CorrespondenceStatus;
use App\Enums\Correspondence\RecipientType;
use App\Models\Correspondence;
use App\Models\CorrespondenceAssignment;
use App\Models\CorrespondenceEvent;
use App\Models\CorrespondenceRecipient;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CorrespondenceService
{
    public function __construct(
        private readonly NumberingService $numberingService
    ) {
    }

    public function createDraft(User $sender, array $data): Correspondence
    {
        return DB::transaction(function () use ($sender, $data) {
            $correspondence = Correspondence::create([
                'sender_id' => $sender->id,
                'subject' => $data['subject'],
                'body' => $data['body'] ?? null,
                'status' => CorrespondenceStatus::DRAFT,
                'priority' => $data['priority'] ?? 'normal',
                'confidentiality' => $data['confidentiality'] ?? 'internal',
                'due_at' => $data['due_at'] ?? null,
                'thread_root_id' => $data['thread_root_id'] ?? null,
                'reply_to_id' => $data['reply_to_id'] ?? null,
            ]);

            $this->syncRecipients($correspondence, $this->normalizeRecipientsInput($data));

            return $correspondence->load(['recipients', 'sender']);
        });
    }

    /**
     * ایجاد نامه و ارسال مستقیم آن در یک تراکنش واحد
     */
    public function createAndSend(User $sender, array $data): Correspondence
    {
        return DB::transaction(function () use ($sender, $data) {
            $correspondence = $this->createDraft($sender, $data);

            return $this->send($correspondence, $sender);
        });
    }

    public function send(Correspondence $correspondence, User $actor): Correspondence
    {
        $actorId = (int) $actor->id;
        $senderId = (int) $correspondence->sender_id;

        if ($senderId !== $actorId) {
            throw new AuthorizationException('فقط فرستنده می‌تواند نامه را ارسال کند.');
        }

        if ($correspondence->status !== CorrespondenceStatus::DRAFT) {
            throw ValidationException::withMessages(['status' => 'فقط پیش‌نویس قابل ارسال است.']);
        }

        $toRecipients = $correspondence->recipients()->where('type', RecipientType::TO)->get();
        if ($toRecipients->isEmpty()) {
            throw ValidationException::withMessages(['to' => 'حداقل یک گیرنده اصلی (to) لازم است.']);
        }

        return DB::transaction(function () use ($correspondence, $actor, $toRecipients) {
            $reference = $this->numberingService->issue();
            $now = now();

            $correspondence->update([
                'reference_number' => $reference,
                'status' => CorrespondenceStatus::SENT,
                'sent_at' => $now,
            ]);

            foreach ($toRecipients as $recipient) {
                $assignment = CorrespondenceAssignment::create([
                    'correspondence_id' => $correspondence->id,
                    'parent_assignment_id' => null,
                    'assigned_by_id' => $actor->id,
                    'assigned_to_id' => $recipient->user_id,
                    'status' => AssignmentStatus::PENDING,
                    'due_at' => $recipient->due_at,
                ]);

                $this->logEvent($correspondence, $actor, CorrespondenceEventType::ASSIGNED, $assignment, [
                    'assigned_to_id' => $recipient->user_id,
                    'root' => true,
                    'due_at' => $recipient->due_at,
                ]);
            }

            $this->logEvent($correspondence, $actor, CorrespondenceEventType::SENT, null, [
                'reference_number' => $reference,
            ]);

            return $correspondence->fresh()->load(['recipients', 'assignments', 'sender']);
        });
    }

    /**
     * حذف نامه‌ای که هنوز توسط هیچ گیرنده یا مسئول ارجاعی مشاهده نشده است
     */
    public function deleteIfUnseen(Correspondence $correspondence, User $actor): void
    {
        $actorId = (int) $actor->id;
        $senderId = (int) $correspondence->sender_id;

        if ($senderId !== $actorId && !$actor->can('manage-correspondences')) {
            throw new AuthorizationException('تنها فرستنده یا مدیر مکاتبات می‌تواند نامه را حذف کند.');
        }

        if ($correspondence->has_been_seen) {
            throw ValidationException::withMessages([
                'correspondence' => 'این نامه توسط گیرندگان مشاهده شده است و امکان حذف ندارد.',
            ]);
        }

        $hasReplies = $correspondence->replies()->exists() || $correspondence->threadChildren()->exists();
        if ($hasReplies) {
            throw ValidationException::withMessages([
                'correspondence' => 'این نامه دارای نامه‌های وابسته است و امکان حذف ندارد.',
            ]);
        }

        DB::transaction(function () use ($correspondence) {
            $attachments = $correspondence->attachments()->get(['id', 'disk', 'storage_path']);

            $correspondence->events()->delete();

            $assignmentIds = $correspondence->assignments()->pluck('id');
            if ($assignmentIds->isNotEmpty()) {
                CorrespondenceAssignment::whereIn('id', $assignmentIds)->update(['parent_assignment_id' => null]);
                CorrespondenceAssignment::whereIn('id', $assignmentIds)->delete();
            }

            foreach ($attachments as $attachment) {
                if ($attachment->storage_path) {
                    Storage::disk($attachment->disk ?: 'public')->delete($attachment->storage_path);
                }
            }
            $correspondence->attachments()->delete();
            $correspondence->recipients()->delete();

            $correspondence->delete();
        });
    }

    /**
     * نرمال‌سازی ورودی گیرندگان به ساختار یکسان [{user_id, type, due_at}]
     */
    private function normalizeRecipientsInput(array $data): array
    {
        $items = [];

        if (!empty($data['recipients']) && is_array($data['recipients'])) {
            foreach ($data['recipients'] as $recipient) {
                $items[] = [
                    'user_id' => (int) ($recipient['user_id'] ?? 0),
                    'type' => $recipient['type'] ?? RecipientType::TO->value,
                    'due_at' => $recipient['due_at'] ?? null,
                ];
            }
        } else {
            foreach (($data['to'] ?? []) as $userId) {
                $items[] = [
                    'user_id' => (int) $userId,
                    'type' => RecipientType::TO->value,
                    'due_at' => null,
                ];
            }
            foreach (($data['cc'] ?? []) as $userId) {
                $items[] = [
                    'user_id' => (int) $userId,
                    'type' => RecipientType::CC->value,
                    'due_at' => null,
                ];
            }
        }

        // حذف تکراری‌ها بر اساس user_id (اولین مورد حفظ می‌شود)
        $seen = [];
        $normalized = [];
        foreach ($items as $item) {
            if ($item['user_id'] <= 0 || isset($seen[$item['user_id']])) {
                continue;
            }
            $seen[$item['user_id']] = true;
            $normalized[] = $item;
        }

        return $normalized;
    }

    public function syncRecipients(Correspondence $correspondence, array $recipients): void
    {
        if ($correspondence->status !== CorrespondenceStatus::DRAFT) {
            throw ValidationException::withMessages(['status' => 'گیرندگان فقط در پیش‌نویس قابل تغییر است.']);
        }

        $correspondence->recipients()->delete();

        foreach ($recipients as $recipient) {
            CorrespondenceRecipient::create([
                'correspondence_id' => $correspondence->id,
                'user_id' => $recipient['user_id'],
                'type' => $recipient['type'] ?? RecipientType::TO->value,
                'due_at' => $recipient['due_at'] ?? null,
            ]);
        }
    }

    /**
     * افزودن گیرنده(ها) به نامه موجود (فقط فرستنده) با مهلت اختیاری و ثبت رویداد
     */
    public function addRecipients(Correspondence $correspondence, User $actor, array $recipients): Correspondence
    {
        $actorId = (int) $actor->id;
        $senderId = (int) $correspondence->sender_id;

        if ($senderId !== $actorId) {
            throw new AuthorizationException('فقط فرستنده می‌تواند گیرنده اضافه کند.');
        }

        return DB::transaction(function () use ($correspondence, $actor, $recipients) {
            foreach ($recipients as $item) {
                $userId = (int) ($item['user_id'] ?? 0);
                if ($userId <= 0) {
                    continue;
                }

                $type = $item['type'] ?? RecipientType::TO->value;
                $dueAt = $item['due_at'] ?? null;

                $recipient = $correspondence->recipients()->where('user_id', $userId)->first();
                if (!$recipient) {
                    $recipient = CorrespondenceRecipient::create([
                        'correspondence_id' => $correspondence->id,
                        'user_id' => $userId,
                        'type' => $type,
                        'due_at' => $dueAt,
                    ]);
                } elseif ($dueAt) {
                    $recipient->update(['due_at' => $dueAt]);
                }

                $assignment = null;
                if ($correspondence->status === CorrespondenceStatus::SENT && $recipient->type === RecipientType::TO) {
                    $exists = $correspondence->assignments()
                        ->where('assigned_to_id', $userId)
                        ->whereNull('parent_assignment_id')
                        ->exists();

                    if (!$exists) {
                        $assignment = CorrespondenceAssignment::create([
                            'correspondence_id' => $correspondence->id,
                            'parent_assignment_id' => null,
                            'assigned_by_id' => $actor->id,
                            'assigned_to_id' => $userId,
                            'status' => AssignmentStatus::PENDING,
                            'due_at' => $recipient->due_at,
                        ]);
                    }
                }

                $this->logEvent($correspondence, $actor, CorrespondenceEventType::ASSIGNED, $assignment, [
                    'recipient_added' => true,
                    'user_id' => $userId,
                    'type' => $recipient->type?->value ?? $recipient->type,
                    'due_at' => $recipient->due_at,
                ]);
            }

            return $correspondence->fresh()->load([
                'sender',
                'recipients',
                'assignments.children',
                'assignments.assignedBy',
                'assignments.assignedTo',
                'assignments.parent.assignedBy',
                'assignments.parent.assignedTo',
                'attachments.uploadedBy',
                'events.actor',
            ]);
        });
    }

    public function logEvent(
        Correspondence $correspondence,
        User $actor,
        CorrespondenceEventType $type,
        ?CorrespondenceAssignment $assignment = null,
        array $metadata = []
    ): CorrespondenceEvent {
        return CorrespondenceEvent::create([
            'correspondence_id' => $correspondence->id,
            'assignment_id' => $assignment?->id,
            'actor_id' => $actor->id,
            'event_type' => $type,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }

    public function markRead(Correspondence $correspondence, User $user): void
    {
        DB::transaction(function () use ($correspondence, $user) {
            $recipient = $correspondence->recipients()->where('user_id', $user->id)->first();
            if ($recipient && !$recipient->read_at) {
                $recipient->update(['read_at' => now()]);
                $this->logEvent($correspondence, $user, CorrespondenceEventType::READ);
            }

            // ثبت زمان مطالعه برای تمام ارجاع‌هایی که مسئول آن‌ها همین کاربر است
            $assignmentIds = $correspondence->assignments()
                ->where('assigned_to_id', $user->id)
                ->whereNull('read_at')
                ->pluck('id');

            if ($assignmentIds->isNotEmpty()) {
                CorrespondenceAssignment::whereIn('id', $assignmentIds)->update(['read_at' => now()]);
            }
        });
    }
}
