<?php

namespace App\Http\Resources\Correspondence;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CorrespondenceAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'correspondence_id' => $this->correspondence_id,
            'parent_assignment_id' => $this->parent_assignment_id,
            'assigned_by_id' => $this->assigned_by_id,
            'assigned_to_id' => $this->assigned_to_id,
            'note' => $this->note,
            'status' => $this->status?->value ?? $this->status,
            'due_at' => $this->due_at,
            'read_at' => $this->read_at,
            'started_at' => $this->started_at,
            'resolved_at' => $this->resolved_at,
            'resolution_note' => $this->resolution_note,
            'archived_at' => $this->archived_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'assigned_by' => $this->whenLoaded('assignedBy'),
            'assigned_to' => $this->whenLoaded('assignedTo'),
            'parent_assignment' => $this->whenLoaded('parent'),
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
