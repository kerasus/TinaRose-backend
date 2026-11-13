<?php

namespace App\Http\Resources\Correspondence;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CorrespondenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'sender_id' => $this->sender_id,
            'thread_root_id' => $this->thread_root_id,
            'reply_to_id' => $this->reply_to_id,
            'subject' => $this->subject,
            'body' => $this->body,
            'status' => $this->status?->value ?? $this->status,
            'priority' => $this->priority?->value ?? $this->priority,
            'confidentiality' => $this->confidentiality?->value ?? $this->confidentiality,
            'due_at' => $this->due_at,
            'sent_at' => $this->sent_at,
            'closed_at' => $this->closed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'sender' => $this->whenLoaded('sender'),
            'reply_to' => $this->whenLoaded('replyTo'),
            'recipients' => CorrespondenceRecipientResource::collection($this->whenLoaded('recipients')),
            'assignments' => CorrespondenceAssignmentResource::collection($this->whenLoaded('assignments')),
            'attachments' => CorrespondenceAttachmentResource::collection($this->whenLoaded('attachments')),
            'events' => CorrespondenceEventResource::collection($this->whenLoaded('events')),
        ];
    }
}
