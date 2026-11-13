<?php

namespace App\Http\Resources\Correspondence;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CorrespondenceEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'correspondence_id' => $this->correspondence_id,
            'assignment_id' => $this->assignment_id,
            'actor_id' => $this->actor_id,
            'event_type' => $this->event_type?->value ?? $this->event_type,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'actor' => $this->whenLoaded('actor'),
        ];
    }
}
