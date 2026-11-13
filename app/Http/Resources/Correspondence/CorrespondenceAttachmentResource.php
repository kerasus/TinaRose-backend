<?php

namespace App\Http\Resources\Correspondence;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CorrespondenceAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'correspondence_id' => $this->correspondence_id,
            'uploaded_by_id' => $this->uploaded_by_id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'created_at' => $this->created_at,
            'path' => $this->path,
            'uploaded_by' => $this->whenLoaded('uploadedBy'),
        ];
    }
}
