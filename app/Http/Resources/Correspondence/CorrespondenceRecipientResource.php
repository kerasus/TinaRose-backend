<?php

namespace App\Http\Resources\Correspondence;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CorrespondenceRecipientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'type' => $this->type?->value ?? $this->type,
            'due_at' => $this->due_at,
            'read_at' => $this->read_at,
            'archived_at' => $this->archived_at,
            'user' => $this->whenLoaded('user'),
        ];
    }
}
