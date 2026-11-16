<?php

namespace App\Services\Correspondence;

use App\Models\CorrespondenceAssignment;
use App\Models\CorrespondenceRecipient;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class InboxService
{
    public function inbox(User $user, Request $request, int $perPage = 20): LengthAwarePaginator
    {
        $query = CorrespondenceRecipient::query()
            ->with(['correspondence.sender', 'user'])
            ->where('correspondence_recipients.user_id', $user->id)
            ->whereHas('correspondence', fn ($q) => $q->whereNotNull('sent_at'));

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        if ($request->filled('reference_number')) {
            $reference = trim((string) $request->get('reference_number'));
            $query->whereHas('correspondence', fn ($q) => $q->where('reference_number', 'like', '%' . $reference . '%'));
        }

        if ($request->filled('type')) {
            $query->where('correspondence_recipients.type', $request->get('type'));
        }

        if ($request->filled('sender_id')) {
            $query->whereHas('correspondence', fn ($q) => $q->where('sender_id', $request->get('sender_id')));
        }

        if ($request->filled('user_id')) {
            $query->where('correspondence_recipients.user_id', $request->get('user_id'));
        }

        $since = $request->get('created_since_date') ?: $request->get('createdSinceDate');
        $till = $request->get('created_till_date') ?: $request->get('createdTillDate');
        if ($since) {
            $query->where('correspondence_recipients.created_at', '>=', $since);
        }
        if ($till) {
            $query->where('correspondence_recipients.created_at', '<=', $till);
        }

        $sortField = $request->get('sortation_field', 'id');
        $sortOrder = strtoupper((string) $request->get('sortation_order', 'desc')) === 'ASC' ? 'asc' : 'desc';
        $allowedSort = ['id', 'created_at', 'read_at', 'type'];
        if (!in_array($sortField, $allowedSort, true)) {
            $sortField = 'id';
        }
        $query->orderBy('correspondence_recipients.' . $sortField, $sortOrder);

        return $query->paginate($perPage);
    }

    public function assignments(User $user, Request $request, int $perPage = 20): LengthAwarePaginator
    {
        $query = CorrespondenceAssignment::query()
            ->with(['correspondence.sender', 'parent', 'assignedBy', 'assignedTo'])
            ->where('correspondence_assignments.assigned_to_id', $user->id);

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        if ($request->filled('reference_number')) {
            $reference = trim((string) $request->get('reference_number'));
            $query->whereHas('correspondence', fn ($q) => $q->where('reference_number', 'like', '%' . $reference . '%'));
        }

        if ($request->filled('subject')) {
            $subject = trim((string) $request->get('subject'));
            $query->whereHas('correspondence', fn ($q) => $q->where('subject', 'like', '%' . $subject . '%'));
        }

        if ($request->filled('assigned_by_id')) {
            $query->where('correspondence_assignments.assigned_by_id', $request->get('assigned_by_id'));
        }

        if ($request->filled('status')) {
            $query->where('correspondence_assignments.status', $request->get('status'));
        }

        $dueSince = $request->get('due_at_since_date') ?: $request->get('dueSinceDate');
        $dueTill = $request->get('due_at_till_date') ?: $request->get('dueTillDate');
        if ($dueSince) {
            $query->where('correspondence_assignments.due_at', '>=', $dueSince);
        }
        if ($dueTill) {
            $query->where('correspondence_assignments.due_at', '<=', $dueTill);
        }

        $createdSince = $request->get('created_since_date') ?: $request->get('createdSinceDate');
        $createdTill = $request->get('created_till_date') ?: $request->get('createdTillDate');
        if ($createdSince) {
            $query->where('correspondence_assignments.created_at', '>=', $createdSince);
        }
        if ($createdTill) {
            $query->where('correspondence_assignments.created_at', '<=', $createdTill);
        }

        $sortField = $request->get('sortation_field', 'id');
        $sortOrder = strtoupper((string) $request->get('sortation_order', 'desc')) === 'ASC' ? 'asc' : 'desc';
        $allowedSort = ['id', 'created_at', 'due_at', 'read_at', 'started_at', 'resolved_at'];
        if (!in_array($sortField, $allowedSort, true)) {
            $sortField = 'id';
        }
        $query->orderBy('correspondence_assignments.' . $sortField, $sortOrder);

        return $query->paginate($perPage);
    }
}
